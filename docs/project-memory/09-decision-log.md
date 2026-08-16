# Decision Log
> Purpose: why things are the way they are, so decisions are not silently undone.
> Project: privacy-forge (public)
> Last updated: 2026-08-17

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

## Bug found and fixed: RetentionSelector re-selected already-anonymised records (Session 12, 2026-08-17)

- **Finding:** `RetentionSelector::query()`'s WHERE clauses only ever
  checked the retention-eligibility columns (`status`/`withdrawn_at` for
  `consent_records`, `status`/`created_at` for `dsar_requests`) — neither
  branch excluded a row `RetentionExecutor::apply()` had already
  anonymised. `anonymise()` deliberately leaves those exact columns
  untouched (the whole point of anonymise vs erase is that the row
  survives), so every subsequent scheduled `retention:execute` run
  re-selected the same already-anonymised row forever: re-running
  `anonymise()` pointlessly, and — the actually harmful part — minting a
  fresh `RetentionExecution`(mode: real) + `DeletionCertificate` on every
  run, each one asserting "N record(s) anonymised" for a record anonymised
  days or weeks earlier. This was caught while investigating a
  cross-session question (does a later retention sweep ever re-process
  data that's already gone?) — the specific scenario asked about
  (DSAR-driven erasure leaving stale data for retention to re-select)
  turned out not to apply (see the finding immediately below), but this
  adjacent, real bug in the same selector was found in the process.
- **Fix:** `RetentionSelector::query()` now also excludes rows whose
  `subject_identifier_hash` already carries the `'anonymised-'` prefix
  both `ConsentRecord::anonymise()`/`DsarRequest::anonymise()` write —
  reusing an existing, already-deliberate marker rather than adding a new
  column. Proven by
  `tests/Feature/RetentionSelectorExclusionTest.php`, which fails against
  the pre-fix selector (a second `retention:execute` run re-anonymises and
  re-certifies) and passes against the fix (second run affects 0 records).
- **Not a parity regression:** ADR-0002's dry-run/execution parity
  guarantee is unaffected — both `preview()` and `execute()` still consume
  the exact same `RetentionSelector::query()`, so the fix's exclusion
  applies identically to both modes.

## Finding, not a bug: DSAR-driven erasure never mutates local consent_records/dsar_requests data (Session 12, 2026-08-17)

- **Finding:** the same cross-session investigation above also checked
  whether a completed DSAR erasure (US-009) could leave `consent_records`/
  `dsar_requests` rows in a state a later retention sweep would need to
  exclude. It does not: `DsarCompletionEvaluator`/
  `DeletionCertificateGenerator` only ever update the `DsarRequest`'s own
  `status` column and write a `DeletionCertificate` — erasure itself is
  dispatched exclusively to *external* connectors over the ADR-0004
  webhook contract, which never touch this application's own database
  rows. `RetentionExecutor` remains the *only* code path that ever
  erases/anonymises `consent_records`/`dsar_requests` content.
- **Demonstrated, not assumed:** `tests/Feature/
  RetentionSelectorExclusionTest.php` runs a real erasure DSAR to
  completion (verify → approve → connector callback success) against a
  subject who also holds a retention-eligible `ConsentRecord`, then
  confirms that record is byte-for-byte unchanged and still correctly
  selected by `RetentionSelector` — there is nothing to exclude on this
  account, because there is nothing DSAR erasure ever touches here.
- **Not logged as a risk:** since there is no code path today that could
  produce the scenario the original question worried about, there is
  nothing open to track in `10-risk-register.md`. If a future session ever
  wires DSAR erasure to also erase this instance's own locally-held data
  (mirroring how `ExportBundleAssembler` already draws export content from
  it), that session would need to revisit `RetentionSelector`'s exclusions
  again at that time.

## RoPA generated on demand, not stored (Session 12, 2026-08-17)

- **Decision:** the RoPA export (US-013/FR-016) is generated fresh on every
  request from `App\Services\RopaGenerator`, reading `ConsentPurpose` (+
  the newly-added `DataCategory`/`RetentionPolicy` join below) at request
  time. There is no `ROPA_RECORD` table and none was added.
- **Why, since no ADR or architecture doc discussion existed to follow:**
  `04-data-model.md`'s ERD never listed a RoPA entity in the first place —
  checked before deciding, per this session's own instruction, rather than
  assumed. A stored RoPA would need its own update path kept in lockstep
  with every purpose/category/policy change it describes, and any gap in
  that lockstep is exactly the kind of "RoPA lied about what we actually
  do" failure Art. 30 exists to prevent. Generating on demand makes that
  class of drift structurally impossible rather than merely disciplined.
- **Not an ADR:** no existing ADR ever committed to a stored-RoPA design,
  so there is nothing to reopen — this is a new, narrow implementation
  decision within US-013's scope, logged here per the same judgement call
  Session 11 made for its own two decision-log-only entries.

## RoPA content: CONSENT_PURPOSE linked to DATA_CATEGORY, PDF via barryvdh/laravel-dompdf (Session 12, 2026-08-17)

- **Finding:** `04-data-model.md`'s ERD never linked `CONSENT_PURPOSE` to
  `DATA_CATEGORY` — `DATA_CATEGORY` existed solely as `RETENTION_POLICY`'s
  governing category (Session 11), scoped to an entire physical table
  (`consent_records`\|`dsar_requests`), not to any one purpose. Art.
  30(1)(c) needs a purpose's retention period and its categories of data
  subjects/personal data; neither was derivable from the existing schema.
- **Decision:** added two nullable columns to `consent_purposes` —
  `data_category_id` (FK to `data_categories`, nullable, `nullOnDelete`)
  and `data_subjects_description` (free text) — an expand-first migration
  per `04-data-model.md`'s own migration approach. `RopaGenerator` joins
  purpose → linked category → that category's currently-active
  `RetentionPolicy` for the retention-period/post-expiry-action columns. A
  purpose with neither field set reports "not yet classified"/"no
  retention policy defined" honestly, rather than fabricating a value.
  `StoreConsentPurposeRequest` accepts both as optional fields so this is
  usable end-to-end via the real endpoint, not only via direct test setup.
- **Known limitation, not fixed this session:** nothing in
  `RetentionPolicyController::store` prevents two independently-created
  `active` `RetentionPolicy` rows for the same `data_category_id` (only
  `::update`'s supersede-then-create path guarantees uniqueness) — a
  pre-existing Session 11 gap. `RopaGenerator` orders by
  `version desc, created_at desc` to stay deterministic if this ever
  occurs, but does not close the underlying gap; out of this session's
  scope (RoPA export, not retention-policy CRUD validation).
- **PDF library:** `barryvdh/laravel-dompdf` (`^3.1`, wrapping
  `dompdf/dompdf`), rendering a Blade view (`resources/views/ropa/
  export.blade.php`). Chosen because it needs no external binary (unlike
  wkhtmltopdf/Snappy) — pure-PHP, so it adds nothing to the container
  image's OS package surface — and is the most widely-used
  Laravel-specific PDF wrapper. This is tooling, not a new architectural
  pattern, so it does not count against the project's 2-new-technology
  cap (ABAC, ASVS L2) confirmed in `12-session-handoff.md`; `composer
  require` completed with no dependency conflicts and no new security
  advisories.

## ADR-0005 — Single-Organisation Data Model (No Tenant Column)
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0005-single-organisation-data-model.md)
- **Decision:** no tenant/org column anywhere in the schema; a
  singleton-constrained settings table instead.
- **Must not be silently reversed because:** adding a dormant tenant column
  "for consistency" would misrepresent this repository's deliberately
  narrow scope and blur the public/private boundary with PR02, which this
  repo's non-goals explicitly guard against.
