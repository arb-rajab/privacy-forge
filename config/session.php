<?php

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'connection' => env('SESSION_CONNECTION', 'default'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],

    'cookie' => env('SESSION_COOKIE', 'privacy_forge_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),

    // T-11 in 06-security-threat-model.md: session hijacking via stolen
    // cookie. These three settings are the actual mitigation, not just
    // documentation. `secure` is left null (Laravel's own default) rather
    // than hardcoded true, because forcing it here would silently break
    // local development over plain http://localhost — it must be set to
    // true via SESSION_SECURE_COOKIE in any real deployment's .env, and
    // that requirement is carried forward explicitly to Session 8's
    // deployment checklist, not left to be discovered by a broken cookie.
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'strict',
    'partitioned' => false,
];
