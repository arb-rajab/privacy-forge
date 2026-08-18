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

    // B-08 (docs/project-memory/11-backlog.md), resolved Session 24: a
    // single fixed, documented demo-viewer credential, re-created by
    // every demo:reset. Deliberately acceptable specifically because
    // Session 24 also descoped ever exposing this to real public
    // internet traffic (docs/project-memory/09-decision-log.md) — a
    // fixed credential is exactly what Demo Instance Data Safety
    // control 2 exists to avoid *for a live public instance* (T-19,
    // 06-security-threat-model.md); with no live public instance, that
    // specific risk does not apply. If this project is ever actually
    // deployed publicly for real, this decision must be revisited
    // first — a per-visitor scoped identity is the right design for
    // that case, not this one.
    'viewer_email' => env('DEMO_VIEWER_EMAIL', 'demo-viewer@privacy-forge.example'),
    'viewer_password' => env('DEMO_VIEWER_PASSWORD', 'privacy-forge-demo-viewer'),
];
