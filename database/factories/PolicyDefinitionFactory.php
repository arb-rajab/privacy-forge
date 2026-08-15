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
}
