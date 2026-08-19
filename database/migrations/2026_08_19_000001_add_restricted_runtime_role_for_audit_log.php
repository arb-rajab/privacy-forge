<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// R-01 (docs/project-memory/10-risk-register.md) / ADR-0003's DB-grant
// layer, closed for real this session (Session 27). Must run via the
// schema-owning connection: `php artisan migrate --database=
// pgsql_migrate` (see config/database.php, README.md, CONTRIBUTING.md).
//
// Why a second, genuinely non-owning role rather than the owner
// revoking its own UPDATE/DELETE: tested empirically against a real
// Postgres instance (docs/project-memory/09-decision-log.md's R-01
// entry) — a table owner CAN revoke its own ordinary DML privileges
// (Postgres allows this), but it can just as trivially GRANT them back
// to itself afterward, since ownership carries the right to alter the
// table's ACL regardless of the ACL's current contents. That makes a
// self-revoke only a soft barrier against exactly the threat this
// exists for: the app's own runtime DB connection running arbitrary SQL
// (a bug, or SQL injection) could simply re-grant itself the privilege
// before tampering. A role that does not own the table and holds no
// grant option cannot do that — GRANT requires ownership, superuser, or
// an existing grant option, none of which this role has.
return new class extends Migration
{
    public function up(): void
    {
        $appUsername = (string) config('database.connections.pgsql.username');
        $appPassword = (string) config('database.connections.pgsql.password');
        $ownerRole = DB::selectOne('SELECT current_user AS role')->role;
        $database = DB::connection()->getDatabaseName();

        if ($appUsername === $ownerRole) {
            throw new RuntimeException(
                'add_restricted_runtime_role_for_audit_log must run as a different '.
                'Postgres role than DB_USERNAME (the app runtime role) — run it via '.
                'the pgsql_migrate connection: php artisan migrate --database=pgsql_migrate. '.
                'Running it as the same role it is trying to restrict would silently '.
                'defeat R-01, the same way ADR-0003 originally found a bare self-REVOKE does.'
            );
        }

        DB::unprepared(sprintf(
            <<<'SQL'
            DO $do$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = %s) THEN
                    CREATE ROLE %s LOGIN PASSWORD %s;
                END IF;
            END
            $do$;
            SQL,
            DB::getPdo()->quote($appUsername),
            $this->quoteIdent($appUsername),
            DB::getPdo()->quote($appPassword),
        ));

        DB::statement(sprintf('GRANT CONNECT ON DATABASE %s TO %s', $this->quoteIdent($database), $this->quoteIdent($appUsername)));
        DB::statement(sprintf('GRANT USAGE ON SCHEMA public TO %s', $this->quoteIdent($appUsername)));

        // Broad grant first (matches what this role needs on every other
        // table — this is not a general read-only role), then the one
        // table this migration exists to narrow.
        DB::statement(sprintf('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %s', $this->quoteIdent($appUsername)));

        // So a *future* migration's new tables/sequences (created via
        // this same owning role) grant to the app role automatically —
        // without this, every future migration would need to remember a
        // manual GRANT, which is exactly the kind of thing that quietly
        // stops happening after a few sessions.
        DB::statement(sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %s', $this->quoteIdent($ownerRole), $this->quoteIdent($appUsername)));
        DB::statement(sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO %s', $this->quoteIdent($ownerRole), $this->quoteIdent($appUsername)));

        // The actual R-01 fix: append (INSERT) and read (SELECT) only on
        // the tamper-evident log, enforced by Postgres itself, not by the
        // application choosing not to expose the action.
        DB::statement(sprintf('REVOKE UPDATE, DELETE ON audit_log_entries FROM %s', $this->quoteIdent($appUsername)));
    }

    public function down(): void
    {
        $appUsername = (string) config('database.connections.pgsql.username');
        $ownerRole = DB::selectOne('SELECT current_user AS role')->role;
        $database = DB::connection()->getDatabaseName();

        DB::statement(sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public REVOKE ALL ON TABLES FROM %s', $this->quoteIdent($ownerRole), $this->quoteIdent($appUsername)));
        DB::statement(sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public REVOKE ALL ON SEQUENCES FROM %s', $this->quoteIdent($ownerRole), $this->quoteIdent($appUsername)));
        DB::statement(sprintf('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('REVOKE USAGE ON SCHEMA public FROM %s', $this->quoteIdent($appUsername)));
        DB::statement(sprintf('REVOKE CONNECT ON DATABASE %s FROM %s', $this->quoteIdent($database), $this->quoteIdent($appUsername)));
        DB::statement(sprintf('DROP ROLE IF EXISTS %s', $this->quoteIdent($appUsername)));
    }

    private function quoteIdent(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
