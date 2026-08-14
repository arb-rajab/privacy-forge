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
}
