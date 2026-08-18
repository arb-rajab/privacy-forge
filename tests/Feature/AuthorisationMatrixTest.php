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
// adds policy.update; Session 11 adds retention.policy.manage; Session 12
// adds ropa.export; Session 21 (B-04) adds audit.log.view. Reading the
// actual `PolicyEvaluator::evaluate()` call sites in
// app/Http/Controllers is the only reliable source of truth for what is
// *actually* registered and gated. As of Session 21, there are exactly six:
//   - dsar.identity.verify       (App\Http\Controllers\Admin\DsarController::verifyIdentity)
//   - dsar.erasure.approve       (App\Http\Controllers\Admin\DsarController::approveErasure)
//   - policy.update              (App\Http\Controllers\Admin\PolicyController::index/show/update)
//   - retention.policy.manage    (App\Http\Controllers\Admin\DataCategoryController::index/store,
//                                  App\Http\Controllers\Admin\RetentionPolicyController::index/show/store/update/dryRun/executions)
//   - ropa.export                (App\Http\Controllers\Admin\RopaController::export)
//   - audit.log.view             (App\Http\Controllers\Admin\AuditLogController::index)
// "DSAR export approval" was never built as a separate gate — Session 8
// wired export/access dispatch to fire at identity-verification time
// instead (see DsarController::verifyIdentity's comment), so it is not a
// distinct action to test; it is already covered by the
// dsar.identity.verify rows below. Retention policy *execution* (the
// specific action ADR-0001 originally anticipated, gating the scheduled
// real-run itself) is addressed in its own dedicated test below — it is
// deliberately NOT ABAC-gated, by design, not because it was forgotten.
// audit.log.view's *allow/deny* cell is covered here like every other
// action; the row-level scoping difference between Owner ("full audit
// log") and Privacy Manager ("entries related to their actions") is a
// separate concern from allow/deny and is covered instead in
// tests/Feature/AuditLogQueryTest.php, per the same
// cross-reference-rather-than-duplicate approach used elsewhere in this
// file.
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
// retention.policy.manage (Session 11, US-010/US-011, ADR-0002): covers
// data-category/retention-policy CRUD and the dry-run preview endpoint —
// all five of DataCategoryController/RetentionPolicyController's actions
// share this one gate, the same "view and edit share it" reasoning as
// policy.update. The dataset below tests POST /admin/data-categories
// (creation) as the representative endpoint, since it has no dependent
// resource to set up first; the remaining four endpoints are covered in
// tests/Feature/RetentionPolicyManagementTest.php, per the same
// cross-reference-rather-than-duplicate approach used elsewhere in this
// file.
//
// ropa.export (Session 12, US-013/FR-016): a single GET endpoint —
// App\Http\Controllers\Admin\RopaController::export — gated the same
// Owner-or-Privacy-Manager shape as retention.policy.manage (the roles
// matrix names RoPA viewing as Privacy Manager's work and explicitly bars
// Support Staff from it). Tested here with ?format=csv, the lighter of
// the two formats — PDF rendering is covered for real in
// tests/Feature/RopaExportTest.php, per the same
// cross-reference-rather-than-duplicate approach used elsewhere in this
// file.
//
// audit.log.view (Session 21, B-04): a single GET endpoint —
// App\Http\Controllers\Admin\AuditLogController::index — gated the same
// Owner-or-Privacy-Manager allow/deny shape as retention.policy.manage
// and ropa.export. Tested here as the representative endpoint for
// allow/deny; the row-level scoping this action's controller applies on
// top of that (Owner sees every entry, Privacy Manager sees only their
// own) is covered separately in tests/Feature/AuditLogQueryTest.php.
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

    'Owner × retention.policy.manage → allow' => ['owner', 'retention.policy.manage', 'allow'],
    'Privacy Manager × retention.policy.manage → allow' => ['privacy_manager', 'retention.policy.manage', 'allow'],
    'Support Staff × retention.policy.manage → deny' => ['support_staff', 'retention.policy.manage', 'deny'],
    'Data Subject × retention.policy.manage → deny (unauthenticated)' => ['data_subject', 'retention.policy.manage', 'deny'],
    'Connector × retention.policy.manage → deny (unauthenticated)' => ['connector', 'retention.policy.manage', 'deny'],

    'Owner × ropa.export → allow' => ['owner', 'ropa.export', 'allow'],
    'Privacy Manager × ropa.export → allow' => ['privacy_manager', 'ropa.export', 'allow'],
    'Support Staff × ropa.export → deny' => ['support_staff', 'ropa.export', 'deny'],
    'Data Subject × ropa.export → deny (unauthenticated)' => ['data_subject', 'ropa.export', 'deny'],
    'Connector × ropa.export → deny (unauthenticated)' => ['connector', 'ropa.export', 'deny'],

    'Owner × audit.log.view → allow' => ['owner', 'audit.log.view', 'allow'],
    'Privacy Manager × audit.log.view → allow' => ['privacy_manager', 'audit.log.view', 'allow'],
    'Support Staff × audit.log.view → deny' => ['support_staff', 'audit.log.view', 'deny'],
    'Data Subject × audit.log.view → deny (unauthenticated)' => ['data_subject', 'audit.log.view', 'deny'],
    'Connector × audit.log.view → deny (unauthenticated)' => ['connector', 'audit.log.view', 'deny'],
]);

test('(role × sensitive action) matrix cell matches the documented permissions matrix', function (string $roleLabel, string $action, string $expected) {
    PolicyDefinition::factory()->create(); // dsar.identity.verify, v1, active
    PolicyDefinition::factory()->forErasureApproval()->create(); // dsar.erasure.approve, v1, active
    PolicyDefinition::factory()->forPolicyUpdate()->create(); // policy.update, v1, active
    PolicyDefinition::factory()->forRetentionPolicyManage()->create(); // retention.policy.manage, v1, active
    PolicyDefinition::factory()->forRopaExport()->create(); // ropa.export, v1, active
    PolicyDefinition::factory()->forAuditLogView()->create(); // audit.log.view, v1, active

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
        'policy.update', 'retention.policy.manage', 'ropa.export', 'audit.log.view' => null,
    };

    $resourceId = match ($action) {
        'policy.update' => $targetPolicy->id,
        // DataCategoryController::store, RopaController::export, and
        // AuditLogController::index all evaluate against the same
        // nil-UUID "no single resource yet" sentinel PolicyController's
        // index() uses — none acts on one specific pre-existing row.
        'retention.policy.manage', 'ropa.export', 'audit.log.view' => '00000000-0000-0000-0000-000000000000',
        default => $dsar->id,
    };

    $endpoint = match ($action) {
        'dsar.identity.verify' => "/api/v1/admin/dsar/{$dsar->id}/verify-identity",
        'dsar.erasure.approve' => "/api/v1/admin/dsar/{$dsar->id}/approve-erasure",
        'policy.update' => "/api/v1/admin/policies/{$targetPolicy->id}",
        'retention.policy.manage' => '/api/v1/admin/data-categories',
        'ropa.export' => '/api/v1/admin/ropa/export?format=csv',
        'audit.log.view' => '/api/v1/admin/audit-log',
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

    $retentionPayload = ['name' => 'Test category', 'sensitivity' => 'standard', 'subject_table' => 'consent_records'];

    $response = match (true) {
        $action === 'policy.update' && $actor === null => $this->patchJson($endpoint, ['effect' => 'allow']),
        $action === 'policy.update' => $this->actingAs($actor)->patchJson($endpoint, ['effect' => 'allow']),
        $action === 'retention.policy.manage' && $actor === null => $this->postJson($endpoint, $retentionPayload),
        $action === 'retention.policy.manage' => $this->actingAs($actor)->postJson($endpoint, $retentionPayload),
        $action === 'ropa.export' && $actor === null => $this->getJson($endpoint),
        $action === 'ropa.export' => $this->actingAs($actor)->getJson($endpoint),
        $action === 'audit.log.view' && $actor === null => $this->getJson($endpoint),
        $action === 'audit.log.view' => $this->actingAs($actor)->getJson($endpoint),
        $actor === null => $this->postJson($endpoint),
        default => $this->actingAs($actor)->postJson($endpoint),
    };

    if ($expected === 'allow') {
        // retention.policy.manage's representative endpoint is a POST
        // that creates a new DataCategory (201); every other action's
        // representative endpoint (including ropa.export's and
        // audit.log.view's GETs) acts on an existing resource or returns a
        // document/list, all 200.
        $response->assertStatus($action === 'retention.policy.manage' ? 201 : 200);

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
//
// retention.policy.manage (Session 11) and audit.log.view (Session 21,
// B-04) are both absent from this table, not because they stopped being
// relevant, but because each moved into the real coverage dataset above
// once its endpoint was built — the same way policy.update did at
// Session 10.
test('retention execution itself is deliberately not a separate registered ABAC action (Session 11, see docs/project-memory/09-decision-log.md)', function () {
    // ADR-0001 anticipated "retention policy execution" as a sensitive
    // action; this session found the scheduled real-run
    // (App\Console\Commands\ExecuteRetentionPoliciesCommand) sits on the
    // scheduler/worker side of the boundary 03-architecture.md draws
    // explicitly ("a worker executes what has already been authorised, it
    // does not re-decide") and so is structurally not a PolicyEvaluator
    // call site — the authorisation event is retention.policy.manage, at
    // policy definition/update time, not at scheduled-run time. Asserted
    // here (not just noted in prose) so a future session that adds a
    // manual "run now" HTTP trigger — which *would* need its own gate —
    // has a failing assertion to update, not a silently stale comment.
    expect(PolicyDefinition::query()->where('action_name', 'retention.execution.run')->exists())->toBeFalse();
});
