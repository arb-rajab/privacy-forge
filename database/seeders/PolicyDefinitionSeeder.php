<?php

namespace Database\Seeders;

use App\Models\PolicyDefinition;
use Illuminate\Database\Seeder;

// R-02 (docs/project-memory/10-risk-register.md): a fresh instance has no
// active PolicyDefinition rows for any of the five registered sensitive
// actions, so ADR-0006's fail-closed PolicyEvaluator denies every staff
// action by default until each row is inserted by hand. These five rows
// are the exact shapes documented in docs/adr (ADR-0001/0006/0007) and
// already exercised in tests via database/factories/
// PolicyDefinitionFactory.php — this seeder writes them directly rather
// than invoking that factory, since a factory is test scaffolding
// (guaranteed present only in a testing/dev install, not a production
// one) and this seeder must work identically on a production instance.
class PolicyDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            // ADR-0001: Owner or Privacy Manager may verify a DSAR
            // subject's identity.
            [
                'action_name' => 'dsar.identity.verify',
                'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']]],
                'resource_conditions' => [],
            ],
            // ADR-0007: separation-of-duties — the approver must differ
            // from whoever verified identity, and only an in-progress
            // erasure can be approved (FR-007/US-006 AC2).
            [
                'action_name' => 'dsar.erasure.approve',
                'subject_conditions' => [
                    'role' => ['in' => ['owner', 'privacy_manager']],
                    'id' => ['not_equals_attribute' => 'resource.identity_verified_by'],
                ],
                'resource_conditions' => [
                    'status' => ['in' => ['in_progress']],
                    'request_type' => ['in' => ['erasure']],
                ],
            ],
            // ADR-0006: policy.update is Owner-only, unlike the other four
            // sensitive actions below.
            [
                'action_name' => 'policy.update',
                'subject_conditions' => ['role' => ['in' => ['owner']]],
                'resource_conditions' => [],
            ],
            // Session 11: retention.policy.manage — Owner or Privacy
            // Manager, US-010/011's day-to-day retention work.
            [
                'action_name' => 'retention.policy.manage',
                'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']]],
                'resource_conditions' => [],
            ],
            // Session 12: ropa.export — Owner or Privacy Manager, US-013/
            // FR-016; the roles matrix explicitly bars Support Staff.
            [
                'action_name' => 'ropa.export',
                'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']]],
                'resource_conditions' => [],
            ],
            // Session 21 (B-04): audit.log.view — Owner or Privacy Manager
            // may reach GET /admin/audit-log at all; row-level scope (full
            // log vs. own-actions-only) is applied in
            // Admin\AuditLogController, not here.
            [
                'action_name' => 'audit.log.view',
                'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']]],
                'resource_conditions' => [],
            ],
        ];

        foreach ($policies as $policy) {
            PolicyDefinition::query()->firstOrCreate(
                ['action_name' => $policy['action_name'], 'status' => 'active'],
                [
                    'version' => 1,
                    'subject_conditions' => $policy['subject_conditions'],
                    'resource_conditions' => $policy['resource_conditions'],
                    'environment_conditions' => [],
                    'effect' => 'allow',
                ],
            );
        }
    }
}
