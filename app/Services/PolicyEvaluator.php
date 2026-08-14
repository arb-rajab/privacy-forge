<?php

namespace App\Services;

use App\Models\PolicyDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

// The single ABAC gate for every sensitive action (ADR-0001). Fail-closed
// by design (ADR-0006): a missing policy, a malformed condition, or any
// other evaluator fault denies — it never falls through to "allow".
// Every decision this method reaches, including fail-closed ones, is
// audit-logged with a policy_id (when one applied) and a reason code.
class PolicyEvaluator
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $resourceAttributes
     * @param  array<string, mixed>  $environmentAttributes
     */
    public function evaluate(
        string $action,
        User $actor,
        string $resourceType,
        string $resourceId,
        array $resourceAttributes = [],
        array $environmentAttributes = [],
    ): PolicyDecision {
        try {
            $policy = PolicyDefinition::query()
                ->where('action_name', $action)
                ->where('status', 'active')
                ->orderByDesc('version')
                ->first();

            if ($policy === null) {
                return $this->denyFailClosed($actor, $action, $resourceType, $resourceId, 'policy_missing');
            }

            $subjectAttributes = ['role' => $actor->role, 'id' => $actor->id];

            $conditionsMet = $this->matchesConditions($policy->subject_conditions, $subjectAttributes)
                && $this->matchesConditions($policy->resource_conditions, $resourceAttributes)
                && $this->matchesConditions($policy->environment_conditions, $environmentAttributes);

            $allowed = $conditionsMet && $policy->effect === 'allow';
            $reasonCode = $allowed ? null : 'policy_conditions_not_met';

            $this->auditLogger->record(
                actorType: 'staff',
                actor: $actor,
                action: $action,
                resourceType: $resourceType,
                resourceId: $resourceId,
                decision: $allowed ? 'allow' : 'deny',
                policyId: $policy->id,
                reasonCode: $reasonCode,
            );

            return new PolicyDecision($allowed, $policy->id, $reasonCode);
        } catch (Throwable $e) {
            Log::error('PolicyEvaluator fault — denying by design (ADR-0006)', [
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);

            return $this->denyFailClosed($actor, $action, $resourceType, $resourceId, 'evaluation_error');
        }
    }

    private function denyFailClosed(
        User $actor,
        string $action,
        string $resourceType,
        string $resourceId,
        string $reasonCode,
    ): PolicyDecision {
        $this->auditLogger->record(
            actorType: 'staff',
            actor: $actor,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            decision: 'deny',
            policyId: null,
            reasonCode: $reasonCode,
        );

        return new PolicyDecision(false, null, $reasonCode);
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @param  array<string, mixed>  $attributes
     */
    private function matchesConditions(?array $conditions, array $attributes): bool
    {
        foreach ($conditions ?? [] as $attribute => $spec) {
            if (! is_array($spec)) {
                // A malformed policy condition (e.g. hand-edited or
                // corrupted at the DB level) — surfaced as an exception so
                // evaluate()'s catch(Throwable) denies fail-closed
                // (ADR-0006) instead of silently skipping the check.
                throw new UnexpectedValueException("Malformed policy condition for attribute \"{$attribute}\".");
            }

            $value = $attributes[$attribute] ?? null;

            if (array_key_exists('in', $spec)) {
                $allowedValues = $spec['in'];
                if (! is_array($allowedValues) || ! in_array($value, $allowedValues, true)) {
                    return false;
                }
            }

            if (array_key_exists('equals', $spec) && $value !== $spec['equals']) {
                return false;
            }
        }

        return true;
    }
}
