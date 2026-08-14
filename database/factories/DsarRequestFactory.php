<?php

namespace Database\Factories;

use App\Models\DsarRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DsarRequest>
 */
class DsarRequestFactory extends Factory
{
    protected $model = DsarRequest::class;

    public function definition(): array
    {
        $identifier = fake()->unique()->safeEmail();

        return [
            'subject_identifier' => $identifier,
            'subject_identifier_hash' => DsarRequest::hashIdentifier($identifier),
            'status_token' => Str::random(64),
            'request_type' => fake()->randomElement(['access', 'export', 'erasure']),
            'status' => 'pending_verification',
        ];
    }
}
