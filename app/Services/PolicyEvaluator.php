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

            // Passed to every matchesConditions() call so a condition on
            // one bag (e.g. subject) can reference an attribute on another
            // (e.g. resource) via the "*_attribute" operators — ADR-0007.
            $attributeBags = [
                'subject' => $subjectAttributes,
                'resource' => $resourceAttributes,
                'environment' => $environmentAttributes,
            ];

            $conditionsMet = $this->matchesConditions($policy->subject_conditions, $subjectAttributes, $attributeBags)
                && $this->matchesConditions($policy->resource_conditions, $resourceAttributes, $attributeBags)
                && $this->matchesConditions($policy->environment_conditions, $environmentAttributes, $attributeBags);

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
     * @param  array<string, array<string, mixed>>  $attributeBags
     */
    private function matchesConditions(?array $conditions, array $attributes, array $attributeBags): bool
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

            if (array_key_exists('not_equals_attribute', $spec)
                && ! $this->attributeDiffersFromReference($value, $spec['not_equals_attribute'], $attributeBags)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolves a "bag.attribute" reference (e.g. "resource.identity_verified_by")
     * against $attributeBags and reports whether $value differs from it —
     * the cross-field comparison operator ADR-0007 adds for rules like
     * separation-of-duties, which compare two attributes of the request
     * to each other rather than one attribute against a fixed constant.
     * A missing or unresolvable reference is treated as "does not differ"
     * (denies) rather than vacuously true, per ADR-0006's fail-closed
     * default — an unresolvable comparison is exactly the kind of
     * ambiguity that must not silently allow.
     *
     * @param  array<string, array<string, mixed>>  $attributeBags
     */
    private function attributeDiffersFromReference(mixed $value, mixed $reference, array $attributeBags): bool
    {
        if (! is_string($reference) || ! str_contains($reference, '.')) {
            throw new UnexpectedValueException('Malformed "not_equals_attribute" reference: expected a "bag.attribute" string.');
        }

        [$bag, $referenceAttribute] = explode('.', $reference, 2);

        if (! array_key_exists($bag, $attributeBags)) {
            throw new UnexpectedValueException("Malformed \"not_equals_attribute\" reference: unknown attribute bag \"{$bag}\".");
        }

        $referenceValue = $attributeBags[$bag][$referenceAttribute] ?? null;

        return $referenceValue !== null && $value !== null && $value !== $referenceValue;
    }
}
