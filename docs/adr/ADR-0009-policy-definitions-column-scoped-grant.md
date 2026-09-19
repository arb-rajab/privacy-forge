# ADR-0009 — Column-Scoped DB Grant Restriction for `policy_definitions`

- **Date:** 2026-09-19
- **Status:** accepted
- **Surfaced during:** T-16's fix session, following R-09
  (`docs/project-memory/10-risk-register.md`) — a prior session found
  `06-security-threat-model.md` claimed a DB-level grant restriction on
  `policy_definitions` that had never actually been built. The doc was
  corrected to stop claiming it; this ADR and its migration are the real
  fix that claim should have described.

## Context

T-16 names the threat directly: "Policy definitions edited directly at
the database level, bypassing the application (e.g. a compromised DB
credential)." By definition, an application-level control cannot address
this — the whole premise of the threat is that the attacker's SQL never
goes through the application at all. So whatever control closes T-16 has
to be enforced by Postgres itself, the same shape as R-01's fix for
`audit_log_entries` (ADR-0003, `2026_08_19_000001_add_restricted_runtime_role_for_audit_log.php`).

`policy_definitions` is not, however, a drop-in match for that precedent.
`audit_log_entries` is append-only by design: the application never
issues `UPDATE`/`DELETE` against it, so revoking both from the runtime
role costs the application nothing. `policy_definitions` is different.
`App\Http\Controllers\Admin\PolicyController::update()` implements
versioning by design (`04-data-model.md`, mirroring `ConsentNotice`):
updating a policy calls `$policy->forceFill(['status' =>
'superseded'])->save()` — a real `UPDATE` against the existing row's
`status` (and `updated_at`) columns — before inserting the new version
row. The application's own runtime credential, `privacy_forge_app`
(the exact role R-01 already scoped down), genuinely needs `UPDATE` on
this table for entirely legitimate traffic. A blanket revoke copied
verbatim from R-01 would break `policy.update` outright.

## Options considered

**A — Copy R-01 exactly: revoke `UPDATE`/`DELETE` entirely.** Rejected.
It doesn't fit this table's own legitimate write pattern (see above) —
this isn't a trade-off to accept, it's simply incorrect for how this
table is used and would take down the `policy.update` action documented
in ADR-0006.

**B — Application-level authorisation only (rely on
`PolicyController`'s existing `policy.update` ABAC gate).** Already true
today, and already tested (`PolicyManagementTest.php`) — but it is not a
response to T-16 at all. T-16's threat model is a credential that reaches
the database *without going through the controller*. No amount of
application-layer gating changes what a raw `psql` session, or a
compromised runtime credential running arbitrary SQL, can do once it
holds valid database credentials. Rejected as non-responsive to the
actual threat, not as a wrong implementation of a right idea.

**C — Column-scoped grant: revoke `DELETE` entirely (the application
never deletes a `policy_definitions` row — superseding always inserts a
new version rather than removing the old one) and narrow `UPDATE` to
exactly the columns the versioning workflow touches (`status`,
`updated_at`), leaving `SELECT`/`INSERT` at full width.** This is what
Postgres's column-level privilege grammar
(`GRANT UPDATE (column, ...) ON table TO role`) exists for. It keeps the
runtime role able to do everything `PolicyController::update()` actually
does, while removing everything a direct-SQL attacker would want that
the application itself never needs: silently rewriting `effect`,
`action_name`, `subject_conditions`, `resource_conditions`, or
`environment_conditions` on an existing (including an already-`active`)
row without creating a new version, and removing rows outright to erase
evidence of a change.

## Decision

**Option C.** A new migration
(`2026_09_19_000002_restrict_runtime_role_grant_for_policy_definitions.php`),
run via the same schema-owning `pgsql_migrate` connection R-01
established (this migration reuses that connection and its same
non-owning-role safety check rather than introducing a second
mechanism):

- `REVOKE DELETE ON policy_definitions FROM privacy_forge_app` — the
  application never deletes a policy row; nothing legitimate is lost.
- `REVOKE UPDATE ON policy_definitions FROM privacy_forge_app` followed
  by `GRANT UPDATE (status, updated_at) ON policy_definitions TO
  privacy_forge_app` — narrows the blanket table-level `UPDATE` grant
  (from R-01's own `ALTER DEFAULT PRIVILEGES`, which applies
  `SELECT, INSERT, UPDATE, DELETE` to every table by default) down to
  only the two columns `PolicyController::update()`'s supersede step
  actually writes.
- `SELECT` and `INSERT` are left untouched — both are needed at full
  width (`INSERT` for every new version row; `SELECT` for `index()`/
  `show()` and for the evaluator's own reads).

This is enforced by Postgres, not by the application choosing not to
issue a different query — the same standard R-01 set with
`AuditLogGrantEnforcementTest.php`. It does not claim to fully close
T-16 the way R-01 fully closes its own analogous threat for
`audit_log_entries`: a compromised runtime credential can still flip an
existing row's `status` directly (that column is, by this table's own
design, meant to be writable by the runtime role) and can still insert a
new "version" row with attacker-chosen conditions, bypassing
`PolicyController`'s ABAC gate and audit logging for that write. Full
closure would require making `policy_definitions` writes as append-only
as `audit_log_entries` is, which would mean redesigning how policy
versioning itself works — out of scope for this session, which found a
fabricated claim and fixed the part of the underlying gap that a DB
grant can actually address without breaking the feature.

## Trade-offs accepted

- This narrows, rather than eliminates, T-16's DB-level attack surface
  on `policy_definitions`. A direct-SQL attacker with the runtime
  credential can still corrupt `status`, and can still insert new rows
  that never went through `policy.update`'s ABAC gate or audit log. What
  it removes is silent, undetected mutation of an existing row's
  substantive fields (`effect`, the three condition columns,
  `action_name`) — the specific tampering shape that would otherwise let
  an attacker rewrite what an *already-evaluated, already-trusted*
  policy row says without leaving the versioning trail `04-data-model.md`
  describes.
- Column-level Postgres grants are less commonly reached for than
  table-level ones and are one more piece of DB-role state a future
  migration touching this table must remember not to blow away with a
  careless blanket `GRANT`. Documented here and in the migration's own
  comments for exactly that reason.

## Consequences

- `06-security-threat-model.md`'s T-16 row is updated to describe this
  control precisely — including its real, bounded scope — rather than
  either the original fabricated claim or a second overstated one.
- R-09 (`10-risk-register.md`) is closed once
  `tests/Feature/PolicyDefinitionGrantEnforcementTest.php` proves, against
  a real Postgres instance connecting as the actual runtime role, that
  the previously-unrestricted `UPDATE`/`DELETE` surface is now narrowed
  exactly as described, without breaking `PolicyController::update()`'s
  own legitimate write path.

## Revisit triggers

- If `policy_definitions` versioning is ever redesigned to be genuinely
  append-only (e.g. status derived from a separate "current version"
  pointer rather than an in-place `UPDATE` on the old row), revisit
  whether `UPDATE` can be revoked from the runtime role entirely,
  matching R-01's stronger guarantee for `audit_log_entries`.
- If a future engagement needs non-repudiable evidence that no
  out-of-band row was ever inserted (not just that existing rows weren't
  silently rewritten), this ADR's scope is not sufficient — that would
  need something closer to `audit_log_entries`' hash-chain/anchoring
  design (ADR-0003) applied to policy versions themselves.
