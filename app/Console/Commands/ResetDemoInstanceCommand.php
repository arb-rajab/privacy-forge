<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
// What this resets: every table holding subject-facing content or
// activity is truncated (consent, DSAR, retention, and audit data),
// then re-seeded to the same minimal baseline `php artisan db:seed`
// produces on a fresh install (the five ABAC policy definitions) plus
// the reference/stub connector.
//
// `users` is included in the truncate list (Session 24, closing B-08)
// — a change from this command's original Session 22 design, which
// deliberately left `users` untouched because no per-visitor demo
// identity mechanism existed yet, and truncating it then would have
// locked out every visitor with nothing to replace it. It's safe now
// because this command always re-creates exactly one fixed,
// documented account (config('demo.viewer_email')/'viewer_password')
// immediately after truncating — a visitor is never actually locked
// out, since the replacement always exists by the time this command
// returns. See config/demo.php's comment for why a single fixed
// credential, normally a bad idea for a public demo (Demo Instance
// Data Safety control 2 / T-19), is an acceptable simplification here:
// Session 24 also descoped ever exposing this to real public traffic.
//
// Populating richer synthetic demo content (sample consent purposes/
// notices/records a visitor can actually explore, not just an empty
// instance) remains undesigned — tracked as B-07 in 11-backlog.md —
// this command's job is safety and a working login, not demo-content
// richness.
class ResetDemoInstanceCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Wipe all subject/activity data and re-seed the synthetic baseline — refuses to run unless DEMO_MODE is enabled';

    // Every table holding subject-facing content or activity, plus
    // `users` (Session 24 — see class comment). Truncated together in
    // one statement, not one table at a time: Postgres refuses to
    // TRUNCATE a table that has a foreign key referencing it from
    // another table unless that other table is truncated in the *same*
    // statement (or CASCADE is used) — true regardless of what order
    // separate statements run in, since the check is against the
    // schema's FK constraints, not current row counts. `users` must be
    // listed here for exactly this reason: `audit_log_entries.actor_user_id`
    // and `dsar_requests.identity_verified_by`/`erasure_approved_by` all
    // reference it. Listing every related table together sidesteps
    // needing CASCADE at all, keeping this command's blast radius
    // exactly this explicit list, not "and whatever else happens to
    // reference it."
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
        'users',
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

        // B-08: the one account a demo visitor can actually log in with.
        // Re-created fresh every reset (users was just truncated above),
        // so this is never a stale credential left over from a prior
        // reset — always exactly this fixed email/password, every time.
        User::create([
            'name' => 'Demo Viewer',
            'email' => config('demo.viewer_email'),
            'password' => Hash::make(config('demo.viewer_password')),
            'role' => 'owner',
            'active' => true,
        ]);

        $this->info('Demo instance reset: all subject/activity data cleared, ABAC policies and the reference connector re-seeded, demo-viewer account re-created.');

        return self::SUCCESS;
    }
}
