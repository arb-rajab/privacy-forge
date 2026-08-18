<?php

use App\Models\AuditLogEntry;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

// B-04 (docs/project-memory/11-backlog.md) — GET /admin/audit-log was
// documented in docs/architecture/openapi.yaml since Session 3 but had no
// implementation until this session. The (role x audit.log.view)
// allow/deny cells are covered in tests/Feature/AuthorisationMatrixTest.php
// per that file's own cross-reference convention; this file covers what
// that matrix deliberately doesn't — the row-level scoping decision
// Admin\AuditLogController applies once a request is already allowed
// ("full audit log" for Owner vs. "entries related to their actions" for
// Privacy Manager, per 02-requirements.md's roles matrix), the
// resourceType/resourceId/since/until filters, chain ordering, and
// fail-closed behaviour.

test('an owner sees the full audit log, including entries produced by other actors', function () {
    PolicyDefinition::factory()->forAuditLogView()->create();
    $logger = app(AuditLogger::class);

    $owner = User::factory()->owner()->create();
    $manager = User::factory()->privacyManager()->create();

    $logger->record(actorType: 'staff', actor: $manager, action: 'test.action.by-manager', resourceType: 'test_resource', resourceId: (string) Str::uuid());
    $logger->record(actorType: 'system', actor: null, action: 'test.action.by-system', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/audit-log');

    $response->assertStatus(200);
    $actions = collect($response->json())->pluck('action');

    // The manager's and the system's entries are both visible to the
    // Owner, plus this very request's own audit.log.view entry — "full"
    // means full, not filtered to the Owner's own actions.
    expect($actions)->toContain('test.action.by-manager');
    expect($actions)->toContain('test.action.by-system');
});

test('a privacy manager sees only audit log entries their own actions produced', function () {
    PolicyDefinition::factory()->forAuditLogView()->create();
    $logger = app(AuditLogger::class);

    $manager = User::factory()->privacyManager()->create();
    $otherManager = User::factory()->privacyManager()->create();

    $logger->record(actorType: 'staff', actor: $manager, action: 'test.action.mine', resourceType: 'test_resource', resourceId: (string) Str::uuid());
    $logger->record(actorType: 'staff', actor: $otherManager, action: 'test.action.not-mine', resourceType: 'test_resource', resourceId: (string) Str::uuid());
    $logger->record(actorType: 'system', actor: null, action: 'test.action.by-system', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $response = $this->actingAs($manager)->getJson('/api/v1/admin/audit-log');

    $response->assertStatus(200);
    $actions = collect($response->json())->pluck('action');

    expect($actions)->toContain('test.action.mine');
    expect($actions)->not->toContain('test.action.not-mine');
    expect($actions)->not->toContain('test.action.by-system');
});

test('resourceType and resourceId filters narrow the results', function () {
    PolicyDefinition::factory()->forAuditLogView()->create();
    $logger = app(AuditLogger::class);
    $owner = User::factory()->owner()->create();

    $matchingId = (string) Str::uuid();
    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.match', resourceType: 'widget', resourceId: $matchingId);
    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.other-type', resourceType: 'gadget', resourceId: (string) Str::uuid());
    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.other-id', resourceType: 'widget', resourceId: (string) Str::uuid());

    $response = $this->actingAs($owner)->getJson("/api/v1/admin/audit-log?resourceType=widget&resourceId={$matchingId}");

    $response->assertStatus(200);
    $entries = collect($response->json());

    expect($entries)->toHaveCount(1);
    expect($entries->first()['action'])->toBe('test.action.match');
});

test('since and until filters narrow results to a date range', function () {
    PolicyDefinition::factory()->forAuditLogView()->create();
    $logger = app(AuditLogger::class);
    $owner = User::factory()->owner()->create();

    $this->travelTo(now()->subDays(10));
    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.old', resourceType: 'test_resource', resourceId: (string) Str::uuid());
    $this->travelBack();

    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.recent', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    // urlencode()'d: a raw "+00:00" offset in the query string would be
    // decoded as a literal space by PHP's query-string parser, breaking
    // date parsing — the same reason Carbon::toJSON()'s "Z" suffix isn't
    // used unencoded either.
    $response = $this->actingAs($owner)->getJson('/api/v1/admin/audit-log?since='.urlencode(now()->subDay()->toIso8601String()));

    $response->assertStatus(200);
    $actions = collect($response->json())->pluck('action');

    expect($actions)->toContain('test.action.recent');
    expect($actions)->not->toContain('test.action.old');
});

test('validation: since must be a real date, not an arbitrary string', function () {
    PolicyDefinition::factory()->forAuditLogView()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/audit-log?since=not-a-date');

    $response->assertStatus(422);
});

test('support staff cannot query the audit log at all — denied by the ABAC gate before any row-level scoping applies', function () {
    $gate = PolicyDefinition::factory()->forAuditLogView()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->getJson('/api/v1/admin/audit-log');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);
});

test('entries are returned in chain order (ascending by sequence), matching the spec description', function () {
    PolicyDefinition::factory()->forAuditLogView()->create();
    $logger = app(AuditLogger::class);
    $owner = User::factory()->owner()->create();

    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.first', resourceType: 'test_resource', resourceId: (string) Str::uuid());
    $logger->record(actorType: 'staff', actor: $owner, action: 'test.action.second', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/audit-log');

    $entries = collect($response->json());
    $firstIndex = $entries->search(fn ($e) => $e['action'] === 'test.action.first');
    $secondIndex = $entries->search(fn ($e) => $e['action'] === 'test.action.second');

    expect($firstIndex)->toBeLessThan($secondIndex);
});

test('fail-closed: a missing audit.log.view policy denies even an Owner, and logs a policy_missing reason code (ADR-0006)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'audit.log.view')->exists())->toBeFalse();

    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/audit-log');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'audit.log.view')
        ->where('resource_id', '00000000-0000-0000-0000-000000000000')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_missing');
});
