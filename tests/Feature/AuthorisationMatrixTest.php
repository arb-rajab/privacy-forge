<?php

use App\Models\AuditLogEntry;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\User;

// NFR-005 — the exhaustive (role × sensitive-action) authorisation test
// suite. docs/project-memory/02-requirements.md (Roles and permissions
// matrix, US-015/FR-013), docs/adr/ADR-0001, docs/adr/ADR-0006,
// docs/adr/ADR-0007.
//
// WHAT "EXHAUSTIVE" MEANS HERE, PRECISELY — read this before extending
// the dataset below:
//
// ADR-0001's Option C names the intended sensitive-action registry:
// DSAR identity verification, DSAR export approval, DSAR erasure
// approval, retention policy execution, and audit log access. ADR-0006
// adds a sixth: policy.update. As of this session, reading the actual
// `PolicyEvaluator::evaluate()` call sites in app/Http/Controllers is the
// only reliable source of truth for what is *actually* registered and
// gated — and there are exactly two:
//   - dsar.identity.verify  (App\Http\Controllers\Admin\DsarController::verifyIdentity)
//   - dsar.erasure.approve  (App\Http\Controllers\Admin\DsarController::approveErasure)
// "DSAR export approval" was never built as a separate gate — Session 8
// wired export/access dispatch to fire at identity-verification time
// instead (see DsarController::verifyIdentity's comment), so it is not a
// distinct action to test; it is already covered by the
// dsar.identity.verify rows below. Retention execution, audit log
// access, and policy.update have no controller, no route, and no
// PolicyDefinition action_name in use anywhere in the codebase — they are
// **not applicable yet**, not silently omitted; see the "Not-yet-built
// sensitive actions" section below for why, one row each.
//
// This means the registered-action count is 2, not the 4 assumed when
// this session was scoped (which also expected a connector-management
// action from Session 8). Session 8 added connector *registration* only
// as an artisan console command (RegisterReferenceConnectorCommand) with
// no HTTP route and no PolicyEvaluator call — it is an ops/CLI action,
// not a staff ABAC action, so it does not belong in this matrix at all.
// This discrepancy against this session's own brief is flagged here
// and in docs/project-memory/12-session-handoff.md, not silently
// resolved by inventing a fourth registered action that doesn't exist.
//
// Roles tested: Owner, Privacy Manager, Support Staff (all real `users`
// rows — the `role` column is a 3-value DB enum, confirmed by reading
// app/Models/User.php) plus Data Subject and Connector. The latter two
// have no `role` enum value and no session-based path to these
// `['web','auth']`-gated admin routes at all (per the roles matrix, both
// are explicitly barred from "the admin UI" / "staff-side screens") — so
// their cells are tested as unauthenticated requests, which the exception
// handler in bootstrap/app.php turns into 401 before PolicyEvaluator ever
// runs. That is a materially different enforcement point (auth middleware,
// not ABAC) from the support_staff denial (which does reach the evaluator
// and gets an audit-logged `policy_conditions_not_met` deny) — both are
// "deny" outcomes per the roles matrix, but only one produces a
// PolicyEvaluator audit trail entry. Both are asserted explicitly below
// rather than treated as interchangeable.
//
// Cross-field (separation-of-duties) cases: these are NOT reimplemented
// here. Session 7 already built and passes them against the real
// endpoints in tests/Feature/DsarErasureApprovalTest.php ("separation of
// duties: the same user who verified identity cannot also approve
// erasure..." / "...a different verifier and approver succeeds..."). This
// matrix's coverage table (docs/project-memory/07-testing-strategy.md)
// lists those two cells as delegated to that file by name, per the
// "cross-reference rather than duplicate, but say so plainly" instruction
// — it does not re-run the same HTTP calls under a different test name.
// This session used the exercise of building this matrix to discover that
// the existing separation-of-duties coverage only exercises the
// privacy_manager role; an Owner self-approval case was genuinely
// missing and has been added this session directly to
// DsarErasureApprovalTest.php (not here), because it belongs beside its
// sibling separation-of-duties tests. See that file and the coverage
// report for why this surfaced a documentation finding, not a code bug:
// 02-requirements.md's Owner row says "Nothing withheld within the
// instance", but ADR-0007's policy row (role in [owner, privacy_manager])
// applies separation-of-duties to Owner too, by deliberate design — this
// is a stale-wording tension in the requirements doc, not a bug to fix by
// weakening the policy (which would reopen ADR-0007).
//
// Fail-closed fault injection (ADR-0006): already covered for BOTH
// registered actions in tests/Feature/DsarIdentityVerificationTest.php
// and tests/Feature/DsarErasureApprovalTest.php (policy_missing and
// evaluation_error reason codes, for each action). Not reimplemented
// here — see the coverage report for the delegation.

dataset('nfr005_role_action_matrix', [
    // [roleLabel, action, expectedDecision]
    'Owner × dsar.identity.verify → allow' => ['owner', 'dsar.identity.verify', 'allow'],
    'Privacy Manager × dsar.identity.verify → allow' => ['privacy_manager', 'dsar.identity.verify', 'allow'],
    'Support Staff × dsar.identity.verify → deny' => ['support_staff', 'dsar.identity.verify', 'deny'],
    'Data Subject × dsar.identity.verify → deny (unauthenticated)' => ['data_subject', 'dsar.identity.verify', 'deny'],
    'Connector × dsar.identity.verify → deny (unauthenticated)' => ['connector', 'dsar.identity.verify', 'deny'],

    'Owner × dsar.erasure.approve → allow' => ['owner', 'dsar.erasure.approve', 'allow'],
    'Privacy Manager × dsar.erasure.approve → allow' => ['privacy_manager', 'dsar.erasure.approve', 'allow'],
    'Support Staff × dsar.erasure.approve → deny' => ['support_staff', 'dsar.erasure.approve', 'deny'],
    'Data Subject × dsar.erasure.approve → deny (unauthenticated)' => ['data_subject', 'dsar.erasure.approve', 'deny'],
    'Connector × dsar.erasure.approve → deny (unauthenticated)' => ['connector', 'dsar.erasure.approve', 'deny'],
]);

test('(role × sensitive action) matrix cell matches the documented permissions matrix', function (string $roleLabel, string $action, string $expected) {
    PolicyDefinition::factory()->create(); // dsar.identity.verify, v1, active
    PolicyDefinition::factory()->forErasureApproval()->create(); // dsar.erasure.approve, v1, active

    // A distinct "someone else" verifier so dsar.erasure.approve's own
    // separation-of-duties condition never fires as a side effect of
    // *this* matrix — that cross-field case is deliberately separate
    // (see file header) and is asserted for real elsewhere.
    $otherVerifier = User::factory()->privacyManager()->create();

    $dsar = match ($action) {
        'dsar.identity.verify' => DsarRequest::factory()->create([
            'status' => 'pending_verification',
        ]),
        'dsar.erasure.approve' => DsarRequest::factory()->create([
            'request_type' => 'erasure',
            'status' => 'in_progress',
            'identity_verified_by' => $otherVerifier->id,
            'identity_verified_at' => now(),
        ]),
    };

    $endpoint = match ($action) {
        'dsar.identity.verify' => "/api/v1/admin/dsar/{$dsar->id}/verify-identity",
        'dsar.erasure.approve' => "/api/v1/admin/dsar/{$dsar->id}/approve-erasure",
    };

    $actor = match ($roleLabel) {
        'owner' => User::factory()->owner()->create(),
        'privacy_manager' => User::factory()->privacyManager()->create(),
        'support_staff' => User::factory()->supportStaff()->create(),
        // Data Subject and Connector are not `users` table roles at all —
        // neither has a session-based path to a ['web','auth'] admin
        // route (roles matrix: Data Subject cannot "view staff-side
        // screens"; Connector cannot "access the admin UI"). Modelled as
        // no authenticated actor, exactly what those principals actually
        // are on this surface.
        'data_subject', 'connector' => null,
    };

    $response = $actor === null
        ? $this->postJson($endpoint)
        : $this->actingAs($actor)->postJson($endpoint);

    if ($expected === 'allow') {
        $response->assertStatus(200);

        $entry = AuditLogEntry::query()
            ->where('action', $action)
            ->where('resource_id', $dsar->id)
            ->first();

        expect($entry)->not->toBeNull();
        expect($entry->decision)->toBe('allow');
        expect($entry->policy_id)->not->toBeNull();

        return;
    }

    // deny
    $response->assertStatus(in_array($roleLabel, ['data_subject', 'connector'], true) ? 401 : 403);

    $entry = AuditLogEntry::query()
        ->where('action', $action)
        ->where('resource_id', $dsar->id)
        ->first();

    if (in_array($roleLabel, ['data_subject', 'connector'], true)) {
        // Never reached the controller, so PolicyEvaluator never ran —
        // no audit entry is the correct outcome here, not a gap. Asserted
        // explicitly so this isn't confused with the support_staff case.
        expect($entry)->toBeNull();
    } else {
        expect($entry)->not->toBeNull();
        expect($entry->decision)->toBe('deny');
        expect($entry->reason_code)->toBe('policy_conditions_not_met');
    }
})->with('nfr005_role_action_matrix');

// Not-yet-built sensitive actions (ADR-0001/ADR-0006 name them; none has a
// controller, route, or PolicyDefinition action_name in the codebase as of
// this session). Asserted here, not just noted in prose, so a future
// session that adds one of these routes has a failing test to update
// rather than a silently-stale comment: each assertion below documents
// exactly what "not applicable yet" is standing in for, and will need to
// be replaced with real matrix rows (see the dataset above) the session
// that builds the corresponding action.
test('retention policy execution has no registered action yet — not applicable to this matrix (US-010/011/012 not started)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'like', 'retention.%')->exists())->toBeFalse();
});

test('audit log access has no registered action yet — not applicable to this matrix (no endpoint gates it)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'like', 'audit_log.%')->exists())->toBeFalse();
});

test('policy.update has no registered action yet — not applicable to this matrix (ADR-0006 names it, R-02 tracks the gap)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'policy.update')->exists())->toBeFalse();
});
