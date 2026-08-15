<?php

namespace Database\Factories;

use App\Models\DataCategory;
use App\Models\RetentionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetentionPolicy>
 */
class RetentionPolicyFactory extends Factory
{
    protected $model = RetentionPolicy::class;

    public function definition(): array
    {
        return [
            'data_category_id' => DataCategory::factory(),
            'retention_period_days' => 30,
            'post_expiry_action' => RetentionPolicy::ACTION_ERASE,
            'status' => 'active',
            'version' => 1,
        ];
    }

    public function anonymise(): static
    {
        return $this->state(fn () => ['post_expiry_action' => RetentionPolicy::ACTION_ANONYMISE]);
    }

    public function deprecated(): static
    {
        return $this->state(fn () => ['status' => 'deprecated']);
    }
}
