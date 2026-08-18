<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Demo Instance Data Safety, control 1 (docs/project-memory/
// 06-security-threat-model.md): "the demo instance resets to its
// synthetic seed state on a fixed schedule... anything entered during a
// demo session is purged at the next reset, not retained indefinitely."
// Designed at Session 4, never implemented until now (docs/project-
// memory/12-session-handoff.md, Session 21's "New gaps found" / this
// session's Part B).
//
// Refuses to run unless config('demo.enabled') is true — the single most
// important line in this file. routes/console.php registers this
// command's scheduler entry unconditionally (the same "registration is
// unconditional, the command decides" shape
// ExecuteRetentionPoliciesCommand already uses for retention execution),
// so a real self-hosted instance that accidentally inherits this
// scheduler entry can never have its data wiped — only an instance that
// has deliberately set DEMO_MODE=true is ever at risk of this command
// doing anything at all.
//
// What this resets, and what it deliberately does not: every table
// holding subject-facing content or activity is truncated (consent,
// DSAR, retention, and audit data), then re-seeded to the same minimal
// baseline `php artisan db:seed` produces on a fresh install (the five
// ABAC policy definitions) plus the reference/stub connector. `users` is
// deliberately left untouched — Demo Instance Data Safety's control 2
// ("no persistent shared admin credential... a temporary, scoped
// demo-session identity per visitor, discarded at reset") is not yet
// designed (see 12-session-handoff.md's Part B plan); truncating `users`
// today would just lock every visitor out with no replacement mechanism,
// which is worse than the gap it would claim to close. Populating richer
// synthetic demo content (sample consent purposes/notices/records a
// visitor can actually explore, not just an empty instance) is also not
// yet designed — tracked in 11-backlog.md — this command's job is safety
// (guarantee nothing persists across a reset), not demo-content richness.
class ResetDemoInstanceCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Wipe all subject/activity data and re-seed the synthetic baseline — refuses to run unless DEMO_MODE is enabled';

    // Every table holding subject-facing content or activity — every
    // migration-defined table except `users` (see class comment for why).
    // Truncated together in one statement, not one table at a time:
    // Postgres refuses to TRUNCATE a table that has a foreign key
    // referencing it from another table unless that other table is
    // truncated in the *same* statement (or CASCADE is used) — true
    // regardless of what order separate statements run in, since the
    // check is against the schema's FK constraints, not current row
    // counts. Listing every related table together sidesteps needing
    // CASCADE at all, keeping this command's blast radius exactly this
    // explicit list, not "and whatever else happens to reference it."
    private const TABLES_TO_TRUNCATE = [
        'deletion_certificates',
        'retention_executions',
        'dsar_connector_tasks',
        'export_bundles',
        'dsar_requests',
        'consent_records',
        'consent_notices',
        'consent_purposes',
        'retention_policies',
        'data_categories',
        'connectors',
        'audit_log_entries',
        'policy_definitions',
    ];

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('demo:reset refuses to run: DEMO_MODE is not enabled. This command is destructive by design and only ever intended for a demo deployment.');

            return self::FAILURE;
        }

        // No RESTART IDENTITY: every one of these tables uses UUID
        // primary keys (HasUuids), not Postgres identity columns, so
        // there is nothing for it to reset except audit_log_entries'
        // `sequence` — a plain nextval()-defaulted bigint (ADR-0003), not
        // a true SQL-standard identity column either, so RESTART IDENTITY
        // wouldn't touch it. Reset that sequence explicitly instead, so a
        // fresh demo chain restarts at sequence 1 (genesis) like a truly
        // fresh instance, not an arbitrary large number.
        DB::statement('TRUNCATE TABLE '.implode(', ', self::TABLES_TO_TRUNCATE));
        DB::statement('ALTER SEQUENCE audit_log_entries_sequence_seq RESTART WITH 1');

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        Artisan::call('connectors:register-reference');

        $this->info('Demo instance reset: all subject/activity data cleared, ABAC policies and the reference connector re-seeded.');

        return self::SUCCESS;
    }
}
