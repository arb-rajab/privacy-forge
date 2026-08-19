<?php

use App\Models\AuditLogEntry;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// R-01 (docs/project-memory/10-risk-register.md): proves the DB-grant
// half of ADR-0003 is real. AuditLogEntry::save()/delete() already throw
// at the application layer (app/Models/AuditLogEntry.php) — that alone
// only proves this codebase's own Eloquent calls are blocked, not that
// the database itself would refuse. Every statement below is raw SQL
// issued through the connection the running application actually uses
// (config('database.default'), the same `pgsql` connection every
// controller/service in this app connects through — no test-only
// elevated credential), specifically to rule out "the app just chooses
// not to expose this" as the reason it fails.
it('connects to the database as the restricted app runtime role, not the schema owner', function () {
    $currentRole = DB::selectOne('SELECT current_user AS role')->role;

    expect($currentRole)
        ->toBe(config('database.connections.pgsql.username'))
        ->not->toBe(config('database.connections.pgsql_migrate.username'));
});

it('rejects a raw SQL UPDATE against audit_log_entries at the Postgres level', function () {
    $entry = app(AuditLogger::class)->record(
        actorType: 'system',
        actor: null,
        action: 'test.r01.update-attempt',
        resourceType: 'test_resource',
        resourceId: (string) Str::uuid(),
    );

    $threw = false;

    try {
        // Wrapped in its own DB::transaction so Laravel issues a SAVEPOINT
        // (RefreshDatabase already has the whole test inside one
        // transaction) — without this, the permission error would poison
        // the outer transaction and the verification query below would
        // fail with "current transaction is aborted", not because
        // anything here is broken.
        DB::transaction(function () use ($entry) {
            DB::statement('UPDATE audit_log_entries SET action = ? WHERE id = ?', ['tampered', $entry->id]);
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getCode())->toBe('42501'); // insufficient_privilege
        expect($e->getMessage())->toContain('permission denied');
    }

    expect($threw)->toBeTrue('Expected Postgres to reject the UPDATE with a permission error; the app runtime role must not have UPDATE on audit_log_entries.');

    expect(AuditLogEntry::query()->find($entry->id)->action)->toBe('test.r01.update-attempt');
});

it('rejects a raw SQL DELETE against audit_log_entries at the Postgres level', function () {
    $entry = app(AuditLogger::class)->record(
        actorType: 'system',
        actor: null,
        action: 'test.r01.delete-attempt',
        resourceType: 'test_resource',
        resourceId: (string) Str::uuid(),
    );

    $threw = false;

    try {
        DB::transaction(function () use ($entry) {
            DB::statement('DELETE FROM audit_log_entries WHERE id = ?', [$entry->id]);
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getCode())->toBe('42501');
        expect($e->getMessage())->toContain('permission denied');
    }

    expect($threw)->toBeTrue('Expected Postgres to reject the DELETE with a permission error; the app runtime role must not have DELETE on audit_log_entries.');

    expect(AuditLogEntry::query()->find($entry->id))->not->toBeNull();
});

it('still allows SELECT and INSERT against audit_log_entries for the app runtime role', function () {
    // Positive control: R-01 must narrow the grant, not accidentally
    // remove the privileges the app genuinely needs to keep working.
    $entry = app(AuditLogger::class)->record(
        actorType: 'system',
        actor: null,
        action: 'test.r01.positive-control',
        resourceType: 'test_resource',
        resourceId: (string) Str::uuid(),
    );

    expect(AuditLogEntry::query()->find($entry->id))->not->toBeNull();
    expect(DB::table('audit_log_entries')->where('id', $entry->id)->exists())->toBeTrue();
});
