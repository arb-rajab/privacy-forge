<?php

namespace Database\Factories;

use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    public function definition(): array
    {
        return [
            'name' => 'Reference Stub Connector',
            'webhook_url' => 'https://connector.example.test/webhook',
            'secret_hash' => Str::random(40),
            'status' => 'active',
            'registered_at' => now(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => 'disabled']);
    }
}
