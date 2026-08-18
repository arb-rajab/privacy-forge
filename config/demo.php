<?php

return [
    // Demo Instance Data Safety (docs/project-memory/06-security-threat-model.md).
    // DEMO_MODE existed in .env.example since Session 4 with no code
    // anywhere reading it — this is the first place anything actually
    // does. Must be false (or unset) for any real self-hosted deployment;
    // App\Console\Commands\ResetDemoInstanceCommand refuses to run unless
    // this is true, specifically so a misconfigured real instance can
    // never have its data wiped by a scheduler entry meant for the demo.
    'enabled' => (bool) env('DEMO_MODE', false),

    // Standard 5-field cron expression, fed straight to
    // Schedule::command('demo:reset')->cron(...) in routes/console.php.
    // The scheduler entry always registers; ResetDemoInstanceCommand
    // itself is what refuses to act when 'enabled' is false, the same
    // "registration is unconditional, the command decides" shape
    // ExecuteRetentionPoliciesCommand already uses.
    'reset_schedule' => env('DEMO_RESET_SCHEDULE', '0 3 * * *'),
];
