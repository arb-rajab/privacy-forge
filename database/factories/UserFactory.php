<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'privacy_manager',
            'active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => 'owner']);
    }

    public function privacyManager(): static
    {
        return $this->state(fn () => ['role' => 'privacy_manager']);
    }

    public function supportStaff(): static
    {
        return $this->state(fn () => ['role' => 'support_staff']);
    }
}
