<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

// R-01 (docs/project-memory/10-risk-register.md): RefreshDatabase's
// migrate:fresh runs against config('database.default') unless told
// otherwise, which is now the app's restricted runtime connection
// (config/database.php) — a role that deliberately cannot create/drop
// tables. Routing just this schema-bootstrap step through the owning
// `pgsql_migrate` connection keeps every other test query (the actual
// app code under test) running as the real, restricted runtime role,
// the same as production — including the R-01 regression test in
// tests/Feature/AuditLogGrantEnforcementTest.php, which would be
// meaningless if tests secretly ran as the owner.
//
// This can't be a plain method override on Tests\TestCase: Pest's
// generated test classes `use RefreshDatabase` directly (per
// tests/Pest.php), and PHP resolves a trait used directly on a class
// ahead of a method inherited from that class's parent — so a
// migrateFreshUsing() override placed on TestCase itself is silently
// shadowed by the trait's own version. Wrapping RefreshDatabase in this
// trait and overriding it here works because, for a single trait, a
// method declared directly on the trait itself takes precedence over
// one it pulls in from another trait it uses.
trait RefreshesDatabaseAsOwner
{
    use RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return array_merge(
            [
                '--database' => 'pgsql_migrate',
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ],
            $this->seeder() ? ['--seeder' => $this->seeder()] : ['--seed' => $this->shouldSeed()],
        );
    }
}
