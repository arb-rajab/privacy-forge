<?php

namespace Database\Factories;

use App\Models\DsarRequest;
use App\Models\ExportBundle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExportBundle>
 */
class ExportBundleFactory extends Factory
{
    protected $model = ExportBundle::class;

    public function definition(): array
    {
        return [
            'dsar_request_id' => DsarRequest::factory(),
            'download_token' => Str::random(64),
            'storage_path' => 'exports/'.Str::uuid().'.json',
            'format' => 'json',
            'signed_url_expires_at' => now()->addHours(72),
        ];
    }

    public function expired(): static
    {
        // Simulates a token that has genuinely passed its expiry. The DB
        // check constraint only bounds signed_url_expires_at from above
        // (never more than 72h past created_at); it doesn't forbid a past
        // expiry, so this satisfies the constraint while still being
        // expired relative to "now" at assertion time.
        return $this->state(fn () => [
            'signed_url_expires_at' => now()->subHours(8),
        ]);
    }
}
