<?php

namespace Database\Factories;

use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetentionExecution>
 */
class RetentionExecutionFactory extends Factory
{
    protected $model = RetentionExecution::class;

    public function definition(): array
    {
        return [
            'retention_policy_id' => RetentionPolicy::factory(),
            'mode' => RetentionExecution::MODE_DRY_RUN,
            'affected_record_count' => 0,
            'certificate_id' => null,
            'executed_at' => now(),
        ];
    }

    public function real(): static
    {
        return $this->state(fn () => ['mode' => RetentionExecution::MODE_REAL]);
    }
}
