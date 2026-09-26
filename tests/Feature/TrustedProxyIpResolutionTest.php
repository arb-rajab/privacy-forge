<?php

use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

// T-03 follow-up (06-security-threat-model.md): this app always runs behind
// a reverse proxy in a real deployment (Caddy — docker/Caddyfile,
// docker-compose.prod.yml). Without a trusted-proxy configuration,
// $request->ip() resolves to the proxy's own address for every request,
// collapsing T-03's per-IP consent rate limit (and any other IP-keyed
// control) into one shared bucket for every real visitor. bootstrap/app.php
// now wires TRUSTED_PROXIES into Laravel's built-in TrustProxies middleware
// (Illuminate\Http\Middleware\TrustProxies::at()/withHeaders(), the same
// static state that middleware itself relies on) — these tests drive that
// exact mechanism directly, the way the framework's own request cycle does,
// rather than merely asserting config values exist.
//
// TrustProxies::at()/withHeaders() store *static* class state (shared for
// the whole PHP process), so every test here resets it before and after —
// otherwise a trusted-proxy configuration set by one test would leak into
// unrelated tests elsewhere in the suite.
beforeEach(fn () => TrustProxies::flushState());
afterEach(fn () => TrustProxies::flushState());

test('with no trusted proxy configured, a spoofed X-Forwarded-For is ignored and ip() is the direct peer', function () {
    config(['consent.capture_rate_limit_per_minute' => 1]);
    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    // Nothing is configured as a trusted proxy (the local/dev default —
    // .env.example ships TRUSTED_PROXIES empty). Two direct callers sharing
    // the same REMOTE_ADDR but claiming different X-Forwarded-For values
    // must still be treated as the *same* caller: the header is never
    // consulted absent a trusted proxy.
    $server = ['REMOTE_ADDR' => '203.0.113.9'];

    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-a@example.com',
    ], $server + ['X-Forwarded-For' => '198.51.100.1'])->assertStatus(201);

    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-b@example.com',
    ], $server + ['X-Forwarded-For' => '198.51.100.2']);

    $response->assertStatus(429);
    expect(ConsentRecord::query()->count())->toBe(1);
});

test('a request through the trusted proxy resolves ip() to the real client, not the proxy', function () {
    config(['consent.capture_rate_limit_per_minute' => 1]);
    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    // Mirrors docker-compose.prod.yml: only the Caddy proxy's address is
    // trusted, and only X-Forwarded-For (among others) is honoured from it.
    TrustProxies::at(['10.10.0.5']);
    TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR);

    // Two different real clients, both arriving via the same trusted
    // proxy (identical REMOTE_ADDR), are correctly treated as two different
    // IPs — proving ip() resolves from X-Forwarded-For, not REMOTE_ADDR,
    // once the sender is trusted.
    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-a@example.com',
    ], ['REMOTE_ADDR' => '10.10.0.5', 'X-Forwarded-For' => '203.0.113.7'])->assertStatus(201);

    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-b@example.com',
    ], ['REMOTE_ADDR' => '10.10.0.5', 'X-Forwarded-For' => '203.0.113.8'])->assertStatus(201);

    // The *same* real client, behind the same proxy, hitting the limit a
    // second time within the minute is still correctly blocked — the
    // limiter is keyed on the real client IP, not "any request from the
    // trusted proxy shares one bucket" nor "every request gets its own".
    $blocked = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-a-again@example.com',
    ], ['REMOTE_ADDR' => '10.10.0.5', 'X-Forwarded-For' => '203.0.113.7']);

    $blocked->assertStatus(429);
    expect(ConsentRecord::query()->count())->toBe(2);
});

test('a spoofed X-Forwarded-For from an address that is not the trusted proxy is not trusted', function () {
    config(['consent.capture_rate_limit_per_minute' => 1]);
    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    // Only the real proxy address is trusted — this attacker connects
    // directly and is not it.
    TrustProxies::at(['10.10.0.5']);
    TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR);

    $attackerServer = ['REMOTE_ADDR' => '198.51.100.50'];

    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'attacker-1@example.com',
    ], $attackerServer + ['X-Forwarded-For' => '1.2.3.4'])->assertStatus(201);

    // Rotating the spoofed X-Forwarded-For does not evade the limit: the
    // untrusted sender's real REMOTE_ADDR is what gets rate-limited.
    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'attacker-2@example.com',
    ], $attackerServer + ['X-Forwarded-For' => '5.6.7.8']);

    $response->assertStatus(429);
    expect(ConsentRecord::query()->count())->toBe(1);
});
