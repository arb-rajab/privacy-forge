<?php

namespace App\Console\Commands;

use App\Models\RetentionPolicy;
use App\Services\AuditLogger;
use App\Services\RetentionExecutor;
use Illuminate\Console\Command;

// US-012/FR-015: scheduled retention execution — routes/console.php
// registers this to run on Laravel's scheduler. Every currently-`active`
// RetentionPolicy is processed each run; "the policy's schedule is due"
// (US-012's acceptance criterion) is this command's own registered
// cadence, not a per-policy cron field — 04-data-model.md's
// RETENTION_POLICY has no such column, and record eligibility is already
// age-based (retention_period_days) rather than schedule-based, so a
// second, per-policy schedule would duplicate that without adding
// anything.
//
// Deliberately NOT gated by PolicyEvaluator (unlike every retention.
// policy.manage-gated HTTP endpoint): this runs on the scheduler/worker
// side of the boundary 03-architecture.md draws explicitly — "a worker
// executes what has already been authorised, it does not re-decide."
// The authorisation event already happened when a Privacy Manager/Owner
// created or updated the policy through the ABAC-gated endpoints; this
// command is that decision's scheduled consequence, not a new decision
// of its own. It still writes its own audit-log entry (US-014 requires
// every retention action logged), with `actor_type: system` and no
// `policy_id` — there is no ABAC policy backing a system-triggered
// action, which is the correct null, not a gap.
class ExecuteRetentionPoliciesCommand extends Command
{
    protected $signature = 'retention:execute';

    protected $description = 'Run every active retention policy, applying its post-expiry action and issuing a deletion certificate';

    public function __construct(
        private readonly RetentionExecutor $executor,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $policies = RetentionPolicy::query()->where('status', 'active')->get();

        if ($policies->isEmpty()) {
            $this->info('No active retention policies to execute.');

            return self::SUCCESS;
        }

        foreach ($policies as $policy) {
            $execution = $this->executor->execute($policy);

            $this->auditLogger->record(
                actorType: 'system',
                actor: null,
                action: 'retention.execution.run',
                resourceType: 'retention_policy',
                resourceId: $policy->id,
            );

            $this->info(sprintf(
                'Policy %s: %d record(s) %s, certificate %s.',
                $policy->id,
                $execution->affected_record_count,
                $policy->post_expiry_action,
                $execution->certificate_id,
            ));
        }

        return self::SUCCESS;
    }
}
