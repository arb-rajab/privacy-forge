<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// R-04/ADR-0003: the periodic external anchoring job the ADR's Decision
// section calls for. routes/console.php registers this on the scheduler.
//
// ADR-0003's Consequences section is explicit that anchor unavailability
// "must trigger an alert, not fail silently" — this command never
// swallows a failed anchor. A storage write failure or any unexpected
// exception both log at 'critical' (Log::critical is this project's
// existing convention for a fault the operator must see — see
// PolicyEvaluator's fail-closed logging) and exit non-zero, so a
// scheduler wired to alert on command failure (Session 8, deployment) has
// something real to alert on. Deliberately NOT gated by PolicyEvaluator,
// same reasoning as ExecuteRetentionPoliciesCommand: this runs on the
// scheduler/worker side of the authorisation boundary, not as a new
// staff-initiated decision.
class AnchorAuditChainCommand extends Command
{
    protected $signature = 'audit:anchor-chain';

    protected $description = 'Anchor the current audit-log chain head to external storage (ADR-0003)';

    public function __construct(private readonly AuditLogger $auditLogger)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->auditLogger->anchorChain();
        } catch (\Throwable $e) {
            Log::critical('Audit chain anchoring raised an unexpected exception.', [
                'exception' => $e->getMessage(),
            ]);
            $this->error('Audit chain anchoring failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $result['anchored']) {
            if ($result['reason'] === 'no_entries') {
                $this->info('No audit log entries exist yet — nothing to anchor.');

                return self::SUCCESS;
            }

            Log::critical('Audit chain anchoring failed — external storage write did not succeed.', $result);
            $this->error(sprintf(
                'Audit chain anchoring failed at sequence %d — see logs for detail.',
                (int) $result['sequence'],
            ));

            return self::FAILURE;
        }

        $this->auditLogger->record(
            actorType: 'system',
            actor: null,
            action: 'audit.chain.anchored',
            resourceType: 'audit_log_entry',
            resourceId: (string) $result['entry_id'],
        );

        $this->info(sprintf(
            'Anchored audit chain at sequence %d (hash %s).',
            (int) $result['sequence'],
            (string) $result['entry_hash'],
        ));

        return self::SUCCESS;
    }
}
