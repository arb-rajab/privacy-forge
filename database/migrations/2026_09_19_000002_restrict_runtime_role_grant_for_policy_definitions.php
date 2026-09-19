<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// R-09 (docs/project-memory/10-risk-register.md) / ADR-0009 — the real
// fix for T-16 (docs/project-memory/06-security-threat-model.md), whose
// prior claim of a DB-level grant restriction on policy_definitions was
// found fabricated and corrected (no such control ever existed). Must
// run via the schema-owning connection, exactly like R-01's sibling
// migration: `php artisan migrate --database=pgsql_migrate`.
//
// Unlike audit_log_entries (R-01), policy_definitions is NOT append-only
// from the application's own point of view:
// App\Http\Controllers\Admin\PolicyController::update() supersedes the
// current row with a genuine `UPDATE ... SET status = ?` before
// inserting the next version — the runtime role needs that. A blanket
// REVOKE UPDATE, copied verbatim from R-01, would break policy.update
// outright. So this narrows rather than eliminates: DELETE is revoked
// entirely (the application never deletes a policy_definitions row), and
// UPDATE is narrowed with Postgres's column-level grant grammar to only
// the columns the versioning workflow actually writes (status,
// updated_at) — see ADR-0009 for the full reasoning and its accepted,
// bounded scope.
return new class extends Migration
{
    public function up(): void
    {
        $appUsername = (string) config('database.connections.pgsql.username');
        $ownerRole = DB::selectOne('SELECT current_user AS role')->role;

        if ($appUsername === $ownerRole) {
            throw new RuntimeException(
                'restrict_runtime_role_grant_for_policy_definitions must run as a '.
                'different Postgres role than DB_USERNAME (the app runtime role) — '.
                'run it via the pgsql_migrate connection: php artisan migrate '.
                '--database=pgsql_migrate. Running it as the same role it is trying '.
                'to restrict would silently defeat R-09/ADR-0009, the same way '.
                'ADR-0003 originally found a bare self-REVOKE does for R-01.'
            );
        }

        // R-01's ALTER DEFAULT PRIVILEGES already granted this role
        // blanket SELECT/INSERT/UPDATE/DELETE on every table, including
        // this one. Revoke DELETE outright — the application never
        // issues one against this table — and revoke UPDATE before
        // re-granting it column-scoped: a bare column-level GRANT does
        // not, on its own, remove the pre-existing whole-table grant.
        DB::statement(sprintf('REVOKE DELETE ON policy_definitions FROM %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('REVOKE UPDATE ON policy_definitions FROM %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('GRANT UPDATE (status, updated_at) ON policy_definitions TO %s', $this->quoteIdent($appUsername)));
    }

    public function down(): void
    {
        $appUsername = (string) config('database.connections.pgsql.username');

        DB::statement(sprintf('REVOKE UPDATE (status, updated_at) ON policy_definitions FROM %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('GRANT UPDATE, DELETE ON policy_definitions TO %s', $this->quoteIdent($appUsername)));
    }

    private function quoteIdent(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
