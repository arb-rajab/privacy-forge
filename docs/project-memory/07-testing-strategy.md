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
code as of Session 11 (2026-08-16).** This is a narrower claim than "100% of
every action this project will ever need," deliberately — see the
discrepancy note below before reading this as the final word on ABAC
coverage.

**Where the evidence lives:** `tests/Feature/AuthorisationMatrixTest.php`
(the exhaustive role × action matrix itself, data-driven — every cell
below is its own executed assertion, not a representative sample),
cross-referencing `tests/Feature/DsarIdentityVerificationTest.php`,
`tests/Feature/DsarErasureApprovalTest.php`, `tests/Feature/
PolicyManagementTest.php`, and (new this session) `tests/Feature/
RetentionPolicyManagementTest.php` for cross-field and fail-closed cases
(see "Delegated coverage" below for exactly what is and isn't
duplicated). The dry-run/real-execution *parity* guarantee itself
(ADR-0002) is a separate, narrower claim from ABAC coverage and is
asserted in its own file, `tests/Feature/RetentionDryRunParityTest.php`,
not folded into this matrix.

**Discrepancy found at Session 9, now fully closed as of Session 11:**
Session 9's brief assumed at least four registered sensitive actions
(`dsar.identity.verify`, `dsar.erasure.approve`, `policy.update`, and a
connector-management action from Session 8). Reading the actual
`PolicyEvaluator::evaluate()` call sites found exactly two at that time.
Session 10 built `policy.update` for real (`App\Http\Controllers\Admin\
PolicyController`, closing `R-03`), bringing the count to three — but also
found the fourth assumed action (connector management) was never going to
be built as an HTTP endpoint (see `docs/project-memory/12-session-
handoff.md`), so "four" itself needed correcting, not just chasing.
Session 11 registers a genuinely new fourth action, `retention.policy.manage`
(`App\Http\Controllers\Admin\DataCategoryController`/
`RetentionPolicyController`) — coincidentally restoring the count to four,
but for a different reason than Session 9 originally assumed. The coverage
table below is honest about testing exactly these four, not treating "four"
as a target that was owed.

#### Coverage table — every (role × registered action) pair

| Role | dsar.identity.verify | dsar.erasure.approve | policy.update | retention.policy.manage |
|---|---|---|---|---|
| Owner | ✅ allow — covered | ✅ allow — covered | ✅ allow — covered | ✅ allow — covered |
| Privacy Manager | ✅ allow — covered | ✅ allow — covered | ✅ deny (`policy_conditions_not_met`) — covered | ✅ allow — covered |
| Support Staff | ✅ deny (`policy_conditions_not_met`) — covered | ✅ deny (`policy_conditions_not_met`) — covered | ✅ deny (`policy_conditions_not_met`) — covered | ✅ deny (`policy_conditions_not_met`) — covered |
| Data Subject | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered |
| Connector | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered | ✅ deny (401, unauthenticated — never reaches the evaluator) — covered |

Unlike `policy.update`, `retention.policy.manage` admits Privacy Manager
(same shape as `dsar.identity.verify`/`dsar.erasure.approve`) — ADR-0006
names `policy.update` Owner-only specifically, but retention policy
definition is Privacy Manager's day-to-day work per US-010/011, so this
column is not a copy of its Owner-only neighbour.

All 20 cells above execute as individual Pest dataset cases in
`AuthorisationMatrixTest.php` (PATCH `/admin/policies/{id}` is the
representative endpoint for `policy.update`'s matrix cells; POST
`/admin/data-categories` is the representative endpoint for
`retention.policy.manage`'s — it has no dependent resource to set up
first, unlike the retention-policy endpoints. `index`/`show` sharing the
same gate, versioning-on-update, and dry-run also sharing the gate are
covered instead in `PolicyManagementTest.php`/
`RetentionPolicyManagementTest.php`, per the same cross-reference-not-
duplicate approach used elsewhere in this file). Support Staff/Privacy-
Manager denials are asserted against a real audit-log entry
(`decision=deny`, `reason_code=policy_conditions_not_met`) produced by a
live `PolicyEvaluator` run; Data Subject/Connector denials are asserted to
produce **no** audit-log entry at all, because the `['web','auth']`
middleware rejects them (`AuthenticationException` → 401 per
`bootstrap/app.php`) before the controller ever calls the evaluator — a
materially different enforcement point from Support Staff's, called out
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
| Missing active policy row | `retention.policy.manage` | `RetentionPolicyManagementTest.php` — "fail-closed: a missing retention.policy.manage policy denies..." (Session 11) |
| Malformed condition spec | `retention.policy.manage` | `RetentionPolicyManagementTest.php` — "fail-closed: a malformed retention.policy.manage condition denies..." (Session 11) |

All four registered actions have fail-closed coverage — broader than
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
| Retention policy execution (the scheduled real-run itself) | Deliberately **not** a separate ABAC action, by design — not "not built yet". See `docs/project-memory/09-decision-log.md` ("Retention execution: scheduler boundary, not a new ABAC action", Session 11): the scheduled run sits on the worker/scheduler side of the boundary `03-architecture.md` draws ("a worker executes what has already been authorised, it does not re-decide"); the authorisation event is `retention.policy.manage`, at policy definition/update time. Asserted in `AuthorisationMatrixTest.php` ("retention execution itself is deliberately not a separate registered ABAC action") so a future session adding a manual "run now" HTTP trigger — which *would* need its own gate — has a failing assertion to update. |
| Audit log access | No endpoint gates viewing the audit log via `PolicyEvaluator`. |

`policy.update` (Session 10) and `retention.policy.manage` (Session 11)
are both removed from this table as they were added to the real coverage
table above — not because either stopped being relevant.

**NFR-005 verdict:** 100% of registered (role × action) pairs covered,
zero discrepancies between observed and expected outcomes. Session 9 found
one real test gap and closed it (Owner self-approval separation-of-duties);
Session 10 closed `R-03` by building `policy.update` for real; Session 11
registers `retention.policy.manage` as a fourth real action and adds it to
this matrix, rather than leaving retention anywhere near a permanent "not
applicable yet" row.

## Accessibility testing
## Performance testing and budgets
## Test data strategy (synthetic only)
## Quality gates in CI
## Known gaps and why they are acceptable
