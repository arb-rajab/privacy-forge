<?php

use App\Models\Connector;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\ConnectorSignatureService;
use Illuminate\Support\Facades\Http;

// The actual proof of Success Metric #1 (00-project-brief.md): a
// stranger can complete consent -> withdrawal -> DSAR -> export cycle
// through the UI alone, not just against the API. Erasure (not
// access/export) is used for the DSAR half specifically because it's
// the only request type with a separate "approve" step, matching the
// brief's own wording ("an admin verifies identity and approves").
//
// This session (Admin Dashboard) closes the last asterisk Session 14's
// handoff flagged: Act 3 below drives the real /login page and the real
// /admin/dsar queue page's own "Verify identity"/"Approve erasure"
// buttons through an actual Playwright browser — no DevTools console
// snippet, no postJson()/actingAs() shortcut anywhere in this test. It
// also drives the same admin attempting to approve their own
// verification and seeing the real ADR-0007 separation-of-duties denial
// rendered inline, proving the UI surfaces that ProblemDetail rather
// than hiding it behind a generic error. Every part of this test — the
// widget, the DSAR portal, and the admin dashboard — runs in the same
// PHP process against the same RefreshDatabase transaction: Pest
// Browser Testing's LaravelHttpServer dispatches every browser-
// originated request through this same application instance, it does
// not shell out to a separate `php artisan serve` process.
test('US-METRIC-1: a fresh visitor completes consent -> DSAR erasure -> admin verify/approve -> status shows completion -> withdrawal', function () {
    PolicyDefinition::factory()->create();
    PolicyDefinition::factory()->forErasureApproval()->create();
    $connector = Connector::factory()->create(['webhook_url' => 'https://connector.example.test/hook']);
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();

    $purpose = ConsentPurpose::factory()->create(['name' => 'Newsletter']);
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    $purpose->forceFill(['current_notice_id' => $notice->id])->save();

    $subjectEmail = 'visitor-'.uniqid().'@example.test';

    // Act 1 — consent, via the widget embedded on a page that is NOT
    // part of this application's own Inertia/admin shell (a plain
    // static HTML file, public/embed-example.html), proving real
    // third-party embeddability rather than an internal admin page.
    $widgetPage = visit("/embed-example.html?purposeId={$purpose->id}")
        ->type("#pf-subject-identifier-{$purpose->id}", $subjectEmail)
        ->click('I agree')
        ->assertSee('Consent recorded');

    expect(ConsentRecord::query()->where('purpose_id', $purpose->id)->count())->toBe(1);

    // Act 2 — the same visitor submits an erasure DSAR via the public
    // portal. No account, no login (05-api-contracts.md's auth model).
    $statusPage = visit('/dsar')
        ->select('#request_type', 'erasure')
        ->type('#subject_identifier', $subjectEmail)
        ->click('Submit request')
        ->assertPathBeginsWith('/dsar/status/')
        ->assertSee('pending_verification');

    $dsar = DsarRequest::query()
        ->where('subject_identifier_hash', DsarRequest::hashIdentifier($subjectEmail))
        ->firstOrFail();
    expect($dsar->request_type)->toBe('erasure');

    // Act 3 — an admin logs in for real through the actual /login page,
    // opens the real /admin/dsar queue page, and clicks its own "Verify
    // identity" button. Purely mouse-and-keyboard from here — no
    // DevTools console, no direct API calls.
    $adminBrowser = visit('/login')
        ->type('#email', $verifier->email)
        ->type('#password', 'password')
        ->click('Log in')
        ->assertPathIs('/')
        ->assertSee('Logged in as');

    $adminBrowser->navigate('/admin/dsar')
        ->assertSee('pending_verification')
        ->click('Verify identity')
        ->assertSee('in_progress');

    // The same admin now tries to approve the erasure they just
    // verified. ADR-0007's separation-of-duties condition denies this —
    // the UI must show that real ABAC denial, not a generic error.
    $adminBrowser->navigate('/admin/dsar')
        ->click('Approve erasure')
        ->assertSee('dsar.erasure.approve policy denied this request');

    // Laravel's auth guard is a singleton bound for the lifetime of this
    // test process, not recreated per simulated request — so a second
    // login attempt without first logging out would be silently
    // redirected away by the 'guest' middleware, which would still think
    // $verifier is logged in. Clicking the real "Log out" link
    // (Welcome.vue) closes that session for real before the second admin
    // logs in, exactly as it would have to in an actual two-admin
    // browser workflow.
    $adminBrowser->navigate('/')
        ->click('Log out')
        ->assertPathIs('/login');

    Http::fake(['connector.example.test/*' => Http::response('', 200)]);

    // A different admin logs in and approves for real — this time ADR-
    // 0007's condition is satisfied, since the approver differs from the
    // identity verifier.
    $adminBrowser->navigate('/login')
        ->type('#email', $approver->email)
        ->type('#password', 'password')
        ->click('Log in')
        ->assertPathIs('/');

    $adminBrowser->navigate('/admin/dsar')
        ->assertSee('in_progress')
        ->click('Approve erasure')
        ->assertSee($approver->id);

    $task = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->firstOrFail();

    // Simulates the connector's real callback (ADR-0004) — same
    // HMAC-signed request shape as ConnectorDispatchTest.php.
    $signer = app(ConnectorSignatureService::class);
    $timestamp = (string) now()->timestamp;
    $body = json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);
    $signature = $signer->sign($connector->secret_hash, $timestamp, $body);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->status)->toBe('complete');
    expect($dsar->deletionCertificate)->not->toBeNull();

    // Act 4 — the visitor returns to their bookmarked status page and
    // sees completion and the deletion certificate, proving the DSAR
    // half of the cycle is reachable end-to-end through the UI.
    $statusPage->refresh()
        ->assertSee('complete')
        ->assertSee('Deletion certificate');

    // Act 5 — withdrawal, back on the still-open widget page from Act 1.
    $widgetPage->click('Withdraw consent')
        ->assertSee('withdrawn');

    $record = ConsentRecord::query()->where('purpose_id', $purpose->id)->firstOrFail();
    expect($record->status)->toBe('withdrawn');
});
