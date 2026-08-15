# Decision Log
> Purpose: why things are the way they are, so decisions are not silently undone.
> Project: privacy-forge (public)
> Last updated: 2026-08-14

Full reasoning for each ADR lives in `docs/adr/`. This log is the
short-form index — read it first, open the linked ADR for the trade-off
detail.

## ADR-0001 — ABAC Policy Model for Sensitive Actions
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0001-abac-policy-model.md)
- **Decision:** custom, versioned-database-row ABAC engine (not framework
  gates, not a third-party library). Separation of duties on erasure
  approval is expressed as a policy condition, not special-cased code.
- **Must not be silently reversed because:** it's the mechanism that makes
  FR-013's audit requirement (policy ID per decision) possible at all, and
  the exhaustive authorisation test suite (NFR-005, Session 7) is written
  against this model specifically.

## ADR-0002 — Retention Dry-Run / Execution Parity
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0002-retention-dry-run-parity.md)
- **Decision:** a single `RetentionSelector` service used by both dry-run
  and real execution; only the executor branches on mode.
- **Must not be silently reversed because:** FR-012 requires structural
  parity, not just tested parity — two separate query paths would
  reintroduce the exact divergence risk this design eliminates.

## ADR-0003 — Audit Log Tamper-Evidence Design
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0003-audit-log-tamper-evidence.md)
- **Decision:** DB-level `UPDATE`/`DELETE` grant revocation **plus**
  hash-chained entries **plus** periodic external anchoring.
- **Must not be silently reversed because:** dropping the anchoring step
  would leave the chain vulnerable to a sufficiently privileged attacker
  who edits entries and recomputes the chain — the anchor is what closes
  that gap, not a nice-to-have.

## ADR-0004 — Connector Webhook Contract Shape
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0004-connector-webhook-contract.md)
- **Decision:** async, queue-based dispatch with signed outbound webhooks
  and a signed inbound callback — not synchronous per-connector calls.
- **Must not be silently reversed because:** FR-009's "independently
  tracked, partial-failure-visible" requirement is not satisfiable with a
  synchronous design without reintroducing head-of-line blocking.

## ADR-0007 — Cross-Field Comparison Operator in Policy Conditions
- **Date:** 2026-08-14 · **Status:** accepted · [Full ADR](../adr/ADR-0007-policy-condition-cross-field-comparison.md)
- **Decision:** extend `PolicyEvaluator`'s condition matcher with a general
  `not_equals_attribute` operator (a `"bag.attribute"` reference resolved
  against subject/resource/environment) rather than special-casing
  separation-of-duties as controller code. Separation-of-duties
  (`dsar.erasure.approve`) is an ordinary policy row like every other rule.
- **Must not be silently reversed because:** ADR-0001 already specified
  separation-of-duties as a policy condition, not application code, so it
  shows up in the same policy registry, audit trail, and exhaustive test
  suite as every other rule. Special-casing it in the controller instead
  would quietly reverse that decision.

## ADR-0006 — Fail-Closed Default for the PolicyEvaluator
- **Date:** 2026-08-12 · **Status:** accepted · [Full ADR](../adr/ADR-0006-policy-evaluator-fail-closed.md)
- **Decision:** the ABAC evaluator denies by default on any error (missing
  policy, malformed condition, exception, data-access failure) — never
  fails open. Every fail-closed denial is logged with a distinguishing
  reason code. Modifying policies is itself added to the sensitive-action
  registry as `policy.update`, Owner-only.
- **Must not be silently reversed because:** fail-open on evaluator error
  would mean a bug or outage silently grants access to the exact actions
  (erasure, export approval, audit log access) this repository exists to
  gate carefully — the opposite of FR-013's intent.

## Documentation correction — Owner row, `02-requirements.md` (Session 10, 2026-08-15)
- **Finding:** Session 9's NFR-005 matrix found `02-requirements.md`'s Owner
  row ("Nothing withheld within the instance") read as exempting Owner from
  separation-of-duties. That is not how ADR-0007 behaves in code — an Owner
  who verified identity on a DSAR is correctly denied when approving that
  DSAR's own erasure, by design.
- **Resolution:** wording corrected to state Owner is subject to the same
  system-wide integrity controls (see the Owner row's footnote). ADR-0007
  itself was **not** reopened or changed — the code was right, the
  documentation was stale.

## Deletion certificate format — shared table, two sources (Session 11, 2026-08-16)
- **Decision:** `DELETION_CERTIFICATE` remains a single shared table for
  both DSAR-driven erasure (US-009) and retention-driven deletion
  (US-012) — this was already the ERD's design since Session 3
  (`RETENTION_EXECUTION ||--o| DELETION_CERTIFICATE`), not a new
  redesign. What Session 11 adds: a DB CHECK constraint
  (`deletion_certificates_exactly_one_source`) requiring exactly one of
  `dsar_request_id`/`retention_execution_id` to be set, so the two
  sources are structurally distinguishable rather than merely
  conventionally so.
- **Alternative considered:** a second, retention-specific certificate
  table. Rejected — the ERD never called for two tables, `summary`/
  `exceptions` mean the same thing regardless of source, and a second
  table would need its own versioning/indexing/testing for no
  differentiating benefit.
- **Not an ADR:** this is an implementation detail within ADR-0002's
  existing scope (a real run "produces a certificate," per that ADR's
  consequences), not a new architectural trade-off — logged here per the
  same judgement call Session 7 made for cross-field vs. fail-closed
  documentation-only decisions.

## Retention execution: scheduler boundary, not a new ABAC action (Session 11, 2026-08-16)
- **Decision:** the scheduled real-run
  (`App\Console\Commands\ExecuteRetentionPoliciesCommand`) is not gated by
  `PolicyEvaluator`. The one new sensitive action this session adds,
  `retention.policy.manage`, covers data-category/retention-policy CRUD
  and the dry-run preview — all staff-initiated, HTTP-request-driven
  actions. The scheduled run itself is triggered by Laravel's scheduler,
  not a staff HTTP request, and `03-architecture.md`'s component
  responsibility table is explicit that a worker/scheduler "executes what
  has already been authorised, it does not re-decide."
- **Must not be silently reversed because:** ADR-0001 anticipated
  "retention policy execution" as a sensitive action; this decision is
  why that specific gate was not built as a separate `PolicyEvaluator`
  call site, and a future session should not assume its absence is an
  oversight. If a manual "run now" HTTP trigger is ever added, *that*
  endpoint would need its own gate (most naturally reusing
  `retention.policy.manage`) — the scheduled path would stay ungated for
  the same reason stated here.
- Still audit-logged (`actor_type: system`, `policy_id: null`) per
  US-014's blanket requirement that every retention action is logged,
  independent of whether an ABAC decision was made.

## ADR-0005 — Single-Organisation Data Model (No Tenant Column)
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0005-single-organisation-data-model.md)
- **Decision:** no tenant/org column anywhere in the schema; a
  singleton-constrained settings table instead.
- **Must not be silently reversed because:** adding a dormant tenant column
  "for consistency" would misrepresent this repository's deliberately
  narrow scope and blur the public/private boundary with PR02, which this
  repo's non-goals explicitly guard against.
