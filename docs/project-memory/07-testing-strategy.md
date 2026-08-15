# Testing Strategy
> Purpose: what we test, at which level, and why that is sufficient
> Project: privacy-forge (public)
> Last updated: 2026-08-15

## Testing philosophy for this project
## Levels
| Level | Tool | Scope | Gate |
|---|---|---|---|
## Security testing

### NFR-005 — exhaustive (role × sensitive-action) authorisation coverage

**Status: satisfied for the registered-action set as it actually exists in
code as of Session 10 (2026-08-15).** This is a narrower claim than "100% of
every action this project will ever need," deliberately — see the
discrepancy note below before reading this as the final word on ABAC
coverage.

**Where the evidence lives:** `tests/Feature/AuthorisationMatrixTest.php`
(the exhaustive role × action matrix itself, data-driven — every cell
below is its own executed assertion, not a representative sample),
cross-referencing `tests/Feature/DsarIdentityVerificationTest.php`,
`tests/Feature/DsarErasureApprovalTest.php`, and (new this session)
`tests/Feature/PolicyManagementTest.php` for cross-field and fail-closed
cases (see "Delegated coverage" below for exactly what is and isn't
duplicated).

**Discrepancy found at Session 9, one third of it closed at Session 10:**
Session 9's brief assumed at least four registered sensitive actions
(`dsar.identity.verify`, `dsar.erasure.approve`, `policy.update`, and a
connector-management action from Session 8). Reading the actual
`PolicyEvaluator::evaluate()` call sites found exactly two at that time.
Session 10 built `policy.update` for real (`App\Http\Controllers\Admin\
PolicyController`, closing `R-03`), bringing the registered-action count to
**three**. Session 10 also evaluated adding an HTTP connector-management
endpoint and decided against it (see `docs/project-memory/12-session-
handoff.md`) — Session 8's `connectors:register-reference` remains an
artisan console command with no HTTP route and no `PolicyEvaluator` call,
so it still isn't a candidate for this matrix. The coverage table below is
honest about testing 3 actions, not 4.

#### Coverage table — every (role × registered action) pair

| Role | dsar.identity.verify | dsar.erasure.approve | policy.update |
|---|---|---|---|
| Owner | ✅ allow — covered | ✅ allow — covered | ✅ allow — covered |
| Privacy Manager | ✅ allow — covered | ✅ allow — covered | ✅ deny (`policy_conditions_not_met`) — covered |
| Support Staff | ✅ deny (`policy_conditions_not_met`) — covered | ✅ deny (`policy_conditions_not_met`) — covered | ✅ deny (`policy_conditions_not_met`) — covered |
| Data Subject | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered |
| Connector | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered |

Unlike the other two actions, `policy.update` denies Privacy Manager —
ADR-0006 names it Owner-only, not Owner-or-Privacy-Manager, so this column
is a genuinely different shape from its neighbours, not a copy-paste.

All 15 cells above execute as individual Pest dataset cases in
`AuthorisationMatrixTest.php` (PATCH `/admin/policies/{id}` is the
representative endpoint for `policy.update`'s matrix cells; `index`/`show`
sharing the same gate is covered instead in `PolicyManagementTest.php`, per
the same cross-reference-not-duplicate approach used elsewhere in this
file). Support Staff/Privacy-Manager denials are asserted against a real
audit-log entry (`decision=deny`, `reason_code=policy_conditions_not_met`)
produced by a live `PolicyEvaluator` run; Data Subject/Connector denials
are asserted to produce **no** audit-log entry at all, because the
`['web','auth']` middleware rejects them (`AuthenticationException` → 401
per `bootstrap/app.php`) before the controller ever calls the evaluator —
a materially different enforcement point from Support Staff's, called out
explicitly rather than treated as equivalent.

#### Cross-field (separation-of-duties) coverage — delegated, not duplicated

| Scenario | Expected | Delegated to |
|---|---|---|
| Privacy Manager verifies, different Privacy Manager/Owner approves | allow | `DsarErasureApprovalTest.php` — "separation of duties: a different verifier and approver succeeds..." |
| Privacy Manager verifies and attempts to approve themselves | deny | `DsarErasureApprovalTest.php` — "separation of duties: the same user who verified identity cannot also approve erasure..." |
| Owner verifies and attempts to approve themselves | deny | `DsarErasureApprovalTest.php` — "separation of duties: an Owner who verified identity also cannot approve erasure..." (**gap found and closed this session** — this case did not exist before; only the Privacy Manager self-approval case had been tested) |

**Finding, not a bug:** the Owner self-approval case above denies, which is
correct per ADR-0007's policy row (`role: {in: [owner, privacy_manager]}`
combined with `not_equals_attribute`) — separation-of-duties applies to
Owner by deliberate design. But `02-requirements.md`'s Owner row reads
"Nothing withheld within the instance," which, read literally, implies
Owner should be exempt. That wording is dated 2026-08-10; ADR-0007 is
dated 2026-08-14 and was written *after* the requirements doc, without a
corresponding update to the Owner row. This is flagged as a
documentation-currency gap for `02-requirements.md`, not fixed by
weakening the policy (which would silently reopen ADR-0007's decision —
out of scope for this session).

#### Fail-closed fault injection (ADR-0006) — delegated, not duplicated

| Fault | Action | Delegated to |
|---|---|---|
| Missing active policy row | `dsar.identity.verify` | `DsarIdentityVerificationTest.php` — "fail-closed: a missing dsar.identity.verify policy denies..." |
| Malformed condition spec | `dsar.identity.verify` | `DsarIdentityVerificationTest.php` — "fail-closed: a malformed policy condition denies..." |
| Superseded (no active) policy | `dsar.identity.verify` | `DsarIdentityVerificationTest.php` — "fail-closed: a policy row present but superseded..." |
| Missing active policy row | `dsar.erasure.approve` | `DsarErasureApprovalTest.php` — "fail-closed: a missing dsar.erasure.approve policy denies..." |
| Malformed `not_equals_attribute` reference | `dsar.erasure.approve` | `DsarErasureApprovalTest.php` — "fail-closed: a malformed not_equals_attribute reference denies..." |
| Missing active policy row | `policy.update` | `PolicyManagementTest.php` — "fail-closed: a missing policy.update policy denies..." (Session 10) |
| Malformed condition spec | `policy.update` | `PolicyManagementTest.php` — "fail-closed: a malformed policy.update condition denies..." (Session 10) |

All three registered actions have fail-closed coverage — broader than
Session 9's original minimum bar of "at least one action."

#### Not-yet-built sensitive actions — explicitly not applicable, not omitted

ADR-0001 anticipates a broader eventual registry than what exists today.
Each row below is asserted in `AuthorisationMatrixTest.php` (that no
`PolicyDefinition` row exists for it yet), so this table goes stale
loudly — as a failing assertion to update — rather than silently, the
session a real action/route is built for one of these.

| Anticipated action | Why not applicable yet |
|---|---|
| DSAR export approval | Not a distinct action — Session 8 wired export/access dispatch to fire at `dsar.identity.verify` time instead, with no separate approval gate. Already covered by the `dsar.identity.verify` rows above. |
| Retention policy execution | US-010/011/012 (retention) not started. |
| Audit log access | No endpoint gates viewing the audit log via `PolicyEvaluator`. |

`policy.update` is removed from this table as of Session 10 — it moved
into the real coverage table above, not because it stopped being relevant.

**NFR-005 verdict:** 100% of registered (role × action) pairs covered,
zero discrepancies between observed and expected outcomes. Session 9 found
one real test gap and closed it (Owner self-approval separation-of-duties);
Session 10 closed `R-03` by building `policy.update` for real and adding it
to this matrix, rather than leaving it as a permanent "not applicable yet"
row.

## Accessibility testing
## Performance testing and budgets
## Test data strategy (synthetic only)
## Quality gates in CI
## Known gaps and why they are acceptable
