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
// There is no admin UI yet (01-scope-and-non-goals.md still lists "a
// richer admin dashboard" as backlog) — the admin verify/approve steps
// below go through the same JSON API a real admin client would call,
// authenticated the same way every other feature test in this suite
// does (Pest's actingAs()). Both this and the browser-driven steps run
// in the same PHP process against the same RefreshDatabase transaction
// — Pest Browser Testing's LaravelHttpServer dispatches every
// browser-originated request through this same application instance,
// it does not shell out to a separate `php artisan serve` process.
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

    // Act 3 — an admin verifies identity, then a *different* admin
    // approves the erasure (ADR-0007 separation-of-duties).
    $this->actingAs($verifier)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    Http::fake(['connector.example.test/*' => Http::response('', 200)]);

    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")
        ->assertStatus(200);

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
