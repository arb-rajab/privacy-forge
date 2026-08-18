<?php

namespace Database\Factories;

use App\Models\PolicyDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyDefinition>
 */
class PolicyDefinitionFactory extends Factory
{
    protected $model = PolicyDefinition::class;

    public function definition(): array
    {
        return [
            'action_name' => 'dsar.identity.verify',
            'version' => 1,
            'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']]],
            'resource_conditions' => [],
            'environment_conditions' => [],
            'effect' => 'allow',
            'status' => 'active',
        ];
    }

    // ADR-0001/ADR-0007 — separation-of-duties (the approver's id must
    // differ from the DSAR's identity_verified_by) and FR-007/US-006 AC2
    // (no approval before verification, no approval on a non-erasure
    // request) expressed as ordinary policy conditions, not controller code.
    public function forErasureApproval(): static
    {
        return $this->state(fn () => [
            'action_name' => 'dsar.erasure.approve',
            'subject_conditions' => [
                'role' => ['in' => ['owner', 'privacy_manager']],
                'id' => ['not_equals_attribute' => 'resource.identity_verified_by'],
            ],
            'resource_conditions' => [
                'status' => ['in' => ['in_progress']],
                'request_type' => ['in' => ['erasure']],
            ],
        ]);
    }

    // ADR-0006 — the policy.update sensitive action itself, Owner-only per
    // that ADR's own wording ("restricted to the Owner role"), unlike
    // dsar.erasure.approve above which also admits privacy_manager.
    public function forPolicyUpdate(): static
    {
        return $this->state(fn () => [
            'action_name' => 'policy.update',
            'subject_conditions' => [
                'role' => ['in' => ['owner']],
            ],
            'resource_conditions' => [],
        ]);
    }

    // Session 11 — retention.policy.manage, the fourth registered sensitive
    // action (data-category/retention-policy CRUD and dry-run). Owner or
    // Privacy Manager, same shape as dsar.identity.verify, not Owner-only
    // like policy.update — retention policy definition is Privacy Manager's
    // day-to-day work per US-010/011 ("As a Privacy Manager, I want to
    // define... preview...").
    public function forRetentionPolicyManage(): static
    {
        return $this->state(fn () => [
            'action_name' => 'retention.policy.manage',
            'subject_conditions' => [
                'role' => ['in' => ['owner', 'privacy_manager']],
            ],
            'resource_conditions' => [],
        ]);
    }

    // Session 12 — ropa.export, the fifth registered sensitive action
    // (US-013/FR-016). Owner or Privacy Manager, same shape as
    // retention.policy.manage — the roles matrix names RoPA viewing as
    // Privacy Manager's day-to-day work and explicitly bars Support Staff
    // from it.
    public function forRopaExport(): static
    {
        return $this->state(fn () => [
            'action_name' => 'ropa.export',
            'subject_conditions' => [
                'role' => ['in' => ['owner', 'privacy_manager']],
            ],
            'resource_conditions' => [],
        ]);
    }

    // Session 21 (B-04) — audit.log.view, the sixth registered sensitive
    // action. Owner or Privacy Manager may reach the endpoint at all
    // (Support Staff is explicitly barred from viewing the audit log at
    // all, per the roles matrix); which rows a Privacy Manager sees once
    // allowed (their own actions only, vs. an Owner's full log) is applied
    // in Admin\AuditLogController, not expressed as a policy condition
    // here — see that controller's class comment for why.
    public function forAuditLogView(): static
    {
        return $this->state(fn () => [
            'action_name' => 'audit.log.view',
            'subject_conditions' => [
                'role' => ['in' => ['owner', 'privacy_manager']],
            ],
            'resource_conditions' => [],
        ]);
    }
}
