<?php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    // Corresponds to STAFF_USER in 04-data-model.md. There is
    // deliberately no separate guard/provider for data subjects (they
    // have no accounts, per 05-api-contracts.md) or connectors (HMAC
    // signature auth, not session-based — see ADR-0004), so neither
    // belongs in this file.
    //
    // NOTE: App\Models\User does not exist yet as of Session 5 — this is
    // a valid PHP class-constant reference (::class resolves to a string
    // literal without triggering autoloading), so it doesn't break config
    // loading, but `php artisan migrate` etc. won't do anything useful
    // with auth until the model and its migration are created at
    // Session 6.
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
