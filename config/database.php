<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        // R-01 (docs/project-memory/10-risk-register.md) / ADR-0003: this
        // is the role the running application (web + worker) actually
        // connects as for every normal request — deliberately NOT the
        // schema owner, so it can have UPDATE/DELETE genuinely revoked on
        // audit_log_entries (see the
        // add_restricted_runtime_role_for_audit_log migration) without
        // being able to just GRANT the privilege back to itself, which a
        // self-revoking owner role could (verified empirically —
        // docs/project-memory/09-decision-log.md's R-01 entry).
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'privacy_forge'),
            'username' => env('DB_USERNAME', 'privacy_forge_app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        // The schema-owning role: creates/owns every table and is the
        // only connection allowed to run `php artisan migrate` (pass
        // `--database=pgsql_migrate`). Never used by the running
        // application itself — that's the entire point of the split
        // above. Defaults to the same role/credentials this project used
        // for everything before R-01 was closed for real, so an instance
        // that hasn't set DB_MIGRATE_* yet still has a working owner role.
        'pgsql_migrate' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'privacy_forge'),
            'username' => env('DB_MIGRATE_USERNAME', 'privacy_forge'),
            'password' => env('DB_MIGRATE_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        // This is the fix for the reported Redis-client mismatch: the
        // Dockerfile installs no PHP Redis extension (no `redis` PECL
        // module — see docker/Dockerfile's docker-php-ext-install line),
        // while composer.json declares predis/predis as a real userland
        // dependency. Laravel's own default here is 'phpredis', which
        // would silently try to use a native extension that was never
        // installed. Defaulting to 'predis' here makes the config match
        // what is actually installed; REDIS_CLIENT in .env can still
        // override this if a future session adds the phpredis extension
        // to the image and wants the faster native client instead.
        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'privacy-forge'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
