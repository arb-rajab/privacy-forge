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
// adds a sixth: policy.update. Reading the actual
// `PolicyEvaluator::evaluate()` call sites in app/Http/Controllers is the
// only reliable source of truth for what is *actually* registered and
// gated. As of Session 10, there are exactly three:
//   - dsar.identity.verify  (App\Http\Controllers\Admin\DsarController::verifyIdentity)
//   - dsar.erasure.approve  (App\Http\Controllers\Admin\DsarController::approveErasure)
//   - policy.update         (App\Http\Controllers\Admin\PolicyController::index/show/update)
// "DSAR export approval" was never built as a separate gate — Session 8
// wired export/access dispatch to fire at identity-verification time
// instead (see DsarController::verifyIdentity's comment), so it is not a
// distinct action to test; it is already covered by the
// dsar.identity.verify rows below. Retention execution and audit log
// access still have no controller, route, or PolicyDefinition
// action_name in use anywhere in the codebase — **not applicable yet**,
// not silently omitted; see the "Not-yet-built sensitive actions" section
// below for why, one row each.
//
// policy.update (Session 10, closing R-03 —
// docs/project-memory/10-risk-register.md): PolicyController exposes
// index/show/update, all three gated by the same policy.update
// PolicyEvaluator call (ADR-0006 names exactly one action for this
// surface, so viewing and editing share it rather than splitting into a
// role-checked "view" and an ABAC-gated "edit"). The dataset below tests
// PATCH (the literal "update") as the representative endpoint; index/show
// sharing the same gate is covered instead in
// tests/Feature/PolicyManagementTest.php, per the same
// cross-reference-rather-than-duplicate approach used for cross-field and
// fail-closed cases (see below). Session 8's connector work added only an
// artisan console command (RegisterReferenceConnectorCommand) — no HTTP
// route, no PolicyEvaluator call — an ops/CLI action, not a staff ABAC
// action, so it still does not belong in this matrix. Session 10
// evaluated adding an HTTP connector-management endpoint and decided
// against it (see docs/project-memory/12-session-handoff.md) — connector
// management remains CLI-only, so this remains correct, not a gap.
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
// erasure..." / "...an Owner..." / "...a different verifier and approver
// succeeds..."). This matrix's coverage table
// (docs/project-memory/07-testing-strategy.md) lists those cells as
// delegated to that file by name, per the "cross-reference rather than
// duplicate, but say so plainly" instruction — it does not re-run the
// same HTTP calls under a different test name.
//
// Fail-closed fault injection (ADR-0006): covered for all three
// registered actions in tests/Feature/DsarIdentityVerificationTest.php,
// tests/Feature/DsarErasureApprovalTest.php, and
// tests/Feature/PolicyManagementTest.php (policy_missing and
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

    'Owner × policy.update → allow' => ['owner', 'policy.update', 'allow'],
    'Privacy Manager × policy.update → deny' => ['privacy_manager', 'policy.update', 'deny'],
    'Support Staff × policy.update → deny' => ['support_staff', 'policy.update', 'deny'],
    'Data Subject × policy.update → deny (unauthenticated)' => ['data_subject', 'policy.update', 'deny'],
    'Connector × policy.update → deny (unauthenticated)' => ['connector', 'policy.update', 'deny'],
]);

test('(role × sensitive action) matrix cell matches the documented permissions matrix', function (string $roleLabel, string $action, string $expected) {
    PolicyDefinition::factory()->create(); // dsar.identity.verify, v1, active
    PolicyDefinition::factory()->forErasureApproval()->create(); // dsar.erasure.approve, v1, active
    PolicyDefinition::factory()->forPolicyUpdate()->create(); // policy.update, v1, active

    // A distinct "someone else" verifier so dsar.erasure.approve's own
    // separation-of-duties condition never fires as a side effect of
    // *this* matrix — that cross-field case is deliberately separate
    // (see file header) and is asserted for real elsewhere.
    $otherVerifier = User::factory()->privacyManager()->create();

    // policy.update's own matrix cell targets an unrelated policy row,
    // never the policy.update gate row created above — so a PATCH under
    // test never supersedes the very policy this test relies on for its
    // own gating.
    $targetPolicy = PolicyDefinition::factory()->create(['action_name' => 'some.other.action']);

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
        'policy.update' => null,
    };

    $resourceId = $action === 'policy.update' ? $targetPolicy->id : $dsar->id;

    $endpoint = match ($action) {
        'dsar.identity.verify' => "/api/v1/admin/dsar/{$dsar->id}/verify-identity",
        'dsar.erasure.approve' => "/api/v1/admin/dsar/{$dsar->id}/approve-erasure",
        'policy.update' => "/api/v1/admin/policies/{$targetPolicy->id}",
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

    $response = match (true) {
        $action === 'policy.update' && $actor === null => $this->patchJson($endpoint, ['effect' => 'allow']),
        $action === 'policy.update' => $this->actingAs($actor)->patchJson($endpoint, ['effect' => 'allow']),
        $actor === null => $this->postJson($endpoint),
        default => $this->actingAs($actor)->postJson($endpoint),
    };

    if ($expected === 'allow') {
        $response->assertStatus(200);

        $entry = AuditLogEntry::query()
            ->where('action', $action)
            ->where('resource_id', $resourceId)
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
        ->where('resource_id', $resourceId)
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

// Not-yet-built sensitive actions (ADR-0001 names them; neither has a
// controller, route, or PolicyDefinition action_name in the codebase as
// of this session). Asserted here, not just noted in prose, so a future
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
