<?php

namespace App\Services;

// The result of a single PolicyEvaluator::evaluate() call (ADR-0001).
// $reasonCode is set on every deny — either a fail-closed reason
// (policy_missing, evaluation_error — ADR-0006) or an ordinary,
// correctly-evaluated one (policy_conditions_not_met) — so a caller or
// an operator reading the audit trail can tell the two apart.
final readonly class PolicyDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $policyId,
        public ?string $reasonCode,
    ) {}
}
