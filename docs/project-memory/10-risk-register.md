# Risk Register
> Purpose: known risks, owned and reviewed rather than forgotten
> Project: privacy-forge (public)
> Last updated: 2026-08-15

| ID | Risk | Category | Impact | Likelihood | Mitigation | Status | Review date |
|---|---|---|---|---|---|---|---|
| R-01 | Audit log lacks DB-level grant revocation (ADR-0003) — app's Postgres role owns the table, so privileges can't be revoked from itself | Security | Medium | Low (requires DB credential compromise) | Hash chain still provides tamper-evidence independently; needs a second, lower-privileged migration-only DB role to fully implement | Open | Before Session 8 (deployment) |
| R-02 | No seeding/bootstrap mechanism exists for `PolicyDefinition` rows — a fresh instance has no active `dsar.identity.verify`, `dsar.erasure.approve`, or (as of Session 10) `policy.update` policy until each is inserted manually. **Unchanged by Session 10**: `PolicyController` (built this session) can only view/supersede an *existing* row — it has no "create the first row for a brand-new action" path, so it doesn't double as the bootstrap step R-02 originally floated as a candidate fix. | Operability | Low (fails safe, not open — ADR-0006 fail-closed denies everyone rather than granting access; this is an availability/usability gap, not a security exposure) | High (true on every fresh install by default) | None yet; a `database/seeders/` bootstrap remains the most direct fix | Open | Before deployment |

## Closed risks

| ID | Risk | Category | Impact | Likelihood | Resolution | Status | Closed |
|---|---|---|---|---|---|---|---|
| R-03 | ADR-0006 commits to `policy.update` as a gated, audited sensitive action. Session 9's NFR-005 matrix found no controller/route implemented this — policies were only editable via CLI/seeders, a documented-but-unimplemented control rather than an active vulnerability. | Security | Was informational/low; would have become a real gap the moment an ungated policy-editing endpoint shipped | N/A (control gap, not an active exploit path) | Session 10 built `GET /admin/policies`, `GET /admin/policies/{id}`, and `PATCH /admin/policies/{id}` (`App\Http\Controllers\Admin\PolicyController`), all three gated by a real `policy.update` `PolicyEvaluator::evaluate()` call — Owner-only, audit-logged, fail-closed (both `policy_missing` and `evaluation_error` reason codes tested). Added to `AuthorisationMatrixTest.php`'s dataset (now 3 actions, 15 cells) and covered by a dedicated `tests/Feature/PolicyManagementTest.php`. All 110 tests pass, Pint clean, Larastan level 8 clean. | **Closed** | Session 10 (2026-08-15) |
