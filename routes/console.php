<?php

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Scheduled jobs land here at Session 6/8/17/22: retention execution
| (ADR-0002), the audit-log chain-anchoring job (ADR-0003), and the
| demo-instance scheduled reset (docs/project-memory/06-security-threat-model.md,
| Demo Instance Data Safety, control 1).
|
*/

use App\Console\Commands\AnchorAuditChainCommand;
use App\Console\Commands\ExecuteRetentionPoliciesCommand;
use App\Console\Commands\ResetDemoInstanceCommand;
use Illuminate\Support\Facades\Schedule;

// US-012: every active retention policy is re-evaluated once daily — see
// ExecuteRetentionPoliciesCommand's own header comment for why this is a
// single system-wide cadence rather than a per-policy schedule.
Schedule::command(ExecuteRetentionPoliciesCommand::class)->daily();

// R-04/ADR-0003: anchoring re-runs hourly, bounding the "unanchored
// window" — audit-log entries written since the last successful anchor,
// which a sufficiently privileged attacker could still rewrite in full
// without verifyAnchors() catching it — to at most an hour. Nothing in
// this project's threat model (single-tenant, self-hosted) calls for a
// tighter cadence, and anchorChain() is idempotent when the chain hasn't
// grown since the last run, so this is cheap even when overdue.
Schedule::command(AnchorAuditChainCommand::class)->hourly();

// This registration is unconditional, the same "registration always
// happens, the command decides" shape as retention execution above —
// ResetDemoInstanceCommand itself refuses to act unless config('demo.
// enabled') is true, so this entry is inert on any real self-hosted
// instance that hasn't explicitly set DEMO_MODE=true. The cron
// expression is configurable (DEMO_RESET_SCHEDULE, .env.example) rather
// than hardcoded, since the actual reset cadence is a deployment-time
// decision, not a compile-time one.
Schedule::command(ResetDemoInstanceCommand::class)->cron(config('demo.reset_schedule'));
