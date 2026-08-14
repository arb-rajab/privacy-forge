<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'active' => 'boolean',
        'password' => 'hashed',
    ];

    public function isPrivilegedFor(string $roleOrAbove): bool
    {
        // $this->role is constrained by the users.role DB enum to exactly
        // these three values, so indexing it can never miss (phpstan
        // confirms this); $roleOrAbove is caller-supplied and unconstrained,
        // so it still needs a fallback.
        $ranking = ['support_staff' => 0, 'privacy_manager' => 1, 'owner' => 2];

        return $ranking[$this->role] >= ($ranking[$roleOrAbove] ?? PHP_INT_MAX);
    }
}
