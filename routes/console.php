<?php

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Scheduled jobs land here at Session 6/8: retention execution
| (ADR-0002), the audit-log chain-anchoring job (ADR-0003), and the
| demo-instance scheduled reset (docs/project-memory/06-security-threat-model.md,
| Demo Instance Data Safety). The audit-log anchor and demo reset remain
| unbuilt — only retention execution (US-012, Session 11) is registered
| below.
|
*/

use App\Console\Commands\ExecuteRetentionPoliciesCommand;
use Illuminate\Support\Facades\Schedule;

// US-012: every active retention policy is re-evaluated once daily — see
// ExecuteRetentionPoliciesCommand's own header comment for why this is a
// single system-wide cadence rather than a per-policy schedule.
Schedule::command(ExecuteRetentionPoliciesCommand::class)->daily();
