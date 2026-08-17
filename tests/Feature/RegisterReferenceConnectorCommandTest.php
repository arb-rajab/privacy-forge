<?php

use App\Models\Connector;

// FR-019/ADR-0004: registration for the one reference/stub connector that
// ships in v1 — no connector registration admin UI exists yet
// (docs/project-memory/12-session-handoff.md), so this artisan command is
// the only registration path this session.

test('the reference connector registration command creates an active connector and prints its secret once', function () {
    expect(Connector::query()->count())->toBe(0);

    $this->artisan('connectors:register-reference', ['--webhook-url' => 'https://stub.example.test/webhook'])
        ->expectsOutputToContain('Connector registered')
        ->expectsOutputToContain('Shared secret')
        ->assertSuccessful();

    expect(Connector::query()->count())->toBe(1);
    $connector = Connector::query()->first();
    expect($connector->status)->toBe('active');
    expect($connector->webhook_url)->toBe('https://stub.example.test/webhook');
    expect($connector->secret_hash)->not->toBeEmpty();
});

// R-06: without an explicit --webhook-url, the command must point at
// this application's own real reference-connector webhook route (not
// config('app.url'), which resolves to the calling container itself —
// see config/connectors.php's reference_connector_base_url comment) so
// `php artisan connectors:register-reference` alone, as the README's
// step 0 now documents, produces a connector whose webhook a fresh
// instance can actually reach.
test('without --webhook-url, the command defaults to this application\'s own real reference-connector route', function () {
    $this->artisan('connectors:register-reference')->assertSuccessful();

    $connector = Connector::query()->firstOrFail();
    expect($connector->webhook_url)->toBe(
        config('connectors.reference_connector_base_url').'/api/reference-connector/webhook'
    );
});
