<?php

namespace Database\Factories;

use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentNotice>
 */
class ConsentNoticeFactory extends Factory
{
    protected $model = ConsentNotice::class;

    public function definition(): array
    {
        return [
            'purpose_id' => ConsentPurpose::factory(),
            'version' => 1,
            'body' => fake()->paragraph(),
            'published_at' => now(),
            'created_at' => now(),
        ];
    }
}
