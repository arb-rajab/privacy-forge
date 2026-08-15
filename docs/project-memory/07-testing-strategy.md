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
code as of Session 9 (2026-08-15).** This is a narrower claim than "100% of
every action this project will ever need," deliberately — see the
discrepancy note below before reading this as the final word on ABAC
coverage.

**Where the evidence lives:** `tests/Feature/AuthorisationMatrixTest.php`
(the exhaustive role × action matrix itself, data-driven — every cell
below is its own executed assertion, not a representative sample),
cross-referencing `tests/Feature/DsarIdentityVerificationTest.php` and
`tests/Feature/DsarErasureApprovalTest.php` for cross-field and
fail-closed cases (see "Delegated coverage" below for exactly what is and
isn't duplicated).

**Discrepancy found and flagged, not silently resolved:** this session's
brief assumed at least four registered sensitive actions (`dsar.identity.
verify`, `dsar.erasure.approve`, `policy.update`, and a connector-management
action from Session 8). Reading the actual `PolicyEvaluator::evaluate()`
call sites (the only reliable source, per this session's own instruction
to not trust the requirements doc's list alone) found exactly **two**
registered actions. `policy.update` is named in ADR-0006 but has no
controller, route, or `PolicyDefinition` action_name in use (`R-02`
already tracks this as an open gap). Session 8's connector work added only
an artisan console command (`connectors:register-reference`) with no HTTP
route and no `PolicyEvaluator` call — an ops/CLI action, not a staff ABAC
action, so it was never a candidate for this matrix. The coverage table
below is honest about testing 2 actions, not 4.

#### Coverage table — every (role × registered action) pair

| Role | dsar.identity.verify | dsar.erasure.approve |
|---|---|---|
| Owner | ✅ allow — covered | ✅ allow — covered |
| Privacy Manager | ✅ allow — covered | ✅ allow — covered |
| Support Staff | ✅ deny (`policy_conditions_not_met`) — covered | ✅ deny (`policy_conditions_not_met`) — covered |
| Data Subject | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered |
| Connector | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered |

All 10 cells above execute as individual Pest dataset cases in
`AuthorisationMatrixTest.php`. Support Staff denials are asserted against
a real audit-log entry (`decision=deny`, `reason_code=policy_conditions_
not_met`) produced by a live `PolicyEvaluator` run; Data Subject/Connector
denials are asserted to produce **no** audit-log entry at all, because the
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

Both registered actions have fail-closed coverage — broader than this
session's minimum bar of "at least one action."

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
| `policy.update` | Named in ADR-0006, not built. Tracked by `R-02`. |

**NFR-005 verdict:** 100% of registered (role × action) pairs covered,
zero discrepancies between observed and expected outcomes, one real test
gap found and closed (Owner self-approval separation-of-duties) — no
authorisation bug found; the only finding is the requirements-doc wording
gap above.

## Accessibility testing
## Performance testing and budgets
## Test data strategy (synthetic only)
## Quality gates in CI
## Known gaps and why they are acceptable
