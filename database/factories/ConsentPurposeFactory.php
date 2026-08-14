<?php

namespace Database\Factories;

use App\Models\ConsentPurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentPurpose>
 */
class ConsentPurposeFactory extends Factory
{
    protected $model = ConsentPurpose::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'lawful_basis' => 'consent',
            'status' => 'active',
            'version' => 1,
        ];
    }
}
