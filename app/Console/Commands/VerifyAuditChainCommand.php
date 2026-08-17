<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;

// ADR-0003's Consequences section requires chain verification to be "a
// documented runbook item ... it must be run routinely, and its result
// must be visible" — this command is that runbook item. It checks both
// layers: verifyChain() (entry-level tamper detection, always available)
// and verifyAnchors() (R-04 — detects a full chain rewrite that
// verifyChain() alone cannot, at the cost of only covering sequences an
// anchor run has already reached).
class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify-chain';

    protected $description = 'Verify the audit-log hash chain and its external anchors (ADR-0003)';

    public function __construct(private readonly AuditLogger $auditLogger)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $chain = $this->auditLogger->verifyChain();

        if (! $chain['valid']) {
            $this->error(sprintf('Hash chain broken at sequence %d.', $chain['brokenAtSequence']));

            return self::FAILURE;
        }

        $this->info('Hash chain valid.');

        $anchors = $this->auditLogger->verifyAnchors();

        if (! $anchors['valid']) {
            $this->error(sprintf(
                'Anchor mismatch at sequence %d — the live database no longer matches a previously anchored hash.',
                $anchors['brokenAtSequence'],
            ));

            return self::FAILURE;
        }

        $this->info(sprintf('Anchors valid (%d checked).', $anchors['checkedAnchors']));

        return self::SUCCESS;
    }
}
