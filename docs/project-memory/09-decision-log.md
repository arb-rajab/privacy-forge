# Decision Log
> Purpose: why things are the way they are, so decisions are not silently undone.
> Project: privacy-forge (public)
> Last updated: 2026-08-18 (Session 20)

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

## ADR-0008 — Retroactive Adoption of Laravel 12.x (correcting undocumented drift)
- **Date:** 2026-08-18 · **Status:** accepted (retroactive) · [Full ADR](../adr/ADR-0008-laravel-12-retroactive-adoption.md)
- **Decision:** Laravel `^12.61.1` (locked at `v12.66.0`) is formally
  adopted as the decided framework version, superseding the Session 0
  ledger's "Laravel 11" allocation. The version itself is not changing —
  the codebase has run exclusively on Laravel 12.x since a correction
  commit early in Session 5 (`97868f1`); this ADR only now records that
  as a real decision instead of silent drift.
- **How this happened (forensic finding, Session 20):** commit `97868f1`
  bumped `laravel/framework` from `^11.0` to `^12.61.1` in its
  `composer.json` diff, while that exact commit's own `CHANGELOG.md` and
  session-handoff entries state at length that the same bump (raised by
  a claimed CVE) was considered and **declined** as unverifiable. The
  narrated decision and the committed diff directly contradict each
  other — the bump was evaluated, written up as rejected, and never
  reverted before the commit was made. Session 6a (`30dffc1`) later
  verified the CVE didn't apply to Laravel 11.x and concluded "no ADR
  needed... this can be considered closed" — correct about the CVE,
  wrong about the repository's actual state, because it trusted Session
  5's narrative instead of opening `composer.json`. No session between
  6a and 19 ever cross-checked the file against the docs either.
- **Must not be silently reversed because:** `composer.lock` has pinned
  Laravel to `v12.66.0` since Session 6a's first real build
  (`d0785f2`) — every session's feature code, migrations, and all 165
  Pest tests have only ever been written and run against Laravel 12.
  Reverting to 11 now would mean executing 14 sessions of code against a
  major version it has never once actually run on, for no functional or
  security benefit (the triggering CVE never applied to 11.x either).
- **Safeguard added:** `.github/workflows/ci.yml`'s new
  `dependency-governance` job fails any PR that changes the
  `laravel/framework` constraint without also touching `docs/adr/` or
  this decision log — closing the exact gap that let this drift go
  unrecorded for 14 sessions.

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

## Embeddable consent widget: a standalone Vite library build, not an Inertia page (Session 13, 2026-08-18)
`01-scope-and-non-goals.md`'s MVP checklist named this the last
undelivered half of item 1 ("capture API + embeddable widget"). The
existing `resources/js/app.js` pipeline (laravel-vite-plugin, Blade
`@vite()` directive, content-hashed manifest-keyed assets) only produces
assets a page *this application* renders — a third-party site embedding
the widget has no Blade template and no manifest, so it needs one
stable URL it can hardcode in a `<script src>` tag. Solved with a second,
independent Vite config (`vite.widget.config.js`) building
`resources/js/widget/main.js` in library mode (IIFE, fixed filename,
`emptyOutDir: false`) straight to `public/widget.js`, alongside the
existing pipeline rather than replacing it. `npm run build` runs both.
Proven genuinely embeddable, not just built that way: `public/
embed-example.html` is a plain static HTML file with zero Blade/Inertia
involvement, and `tests/Browser/DsarLifecycleTest.php` drives it as a
real browser would.

## DSAR status page: a UI shell that forwards the existing signed link, not a new endpoint (Session 13, 2026-08-18)
The brief for this session was explicit that the DSAR portal UI must
call the contracts already in `docs/architecture/openapi.yaml` — no new
endpoints. `GET /api/v1/dsar/status/{signedToken}` returns JSON and its
signature is a hash over that exact path plus query string (Laravel's
`hasValidSignature()`); a page at a different path could not reuse the
same signature. Rather than add a second signing scheme or move the
JSON route, `routes/web.php`'s `/dsar/status/{signedToken}` is a plain
Inertia shell that takes no position on the query string's validity at
all — `DsarStatus.vue` reads `window.location.search` and calls
`fetch('/api/v1/dsar/status/'+signedToken+search)` client-side, i.e. the
literal, unmodified signed URL `DsarController::submit` already mints.
An invalid or expired signature still resolves correctly (a 410 from the
real endpoint, shown as "expired"); nothing about the underlying contract
or its signing changed.

## Pest Browser Testing (`pestphp/pest-plugin-browser`) added for the Success-Metric-1 E2E test (Session 13, 2026-08-18)
The brief asked for a genuine end-to-end proof of the consent →
withdrawal → DSAR → export/erasure cycle, not just "the pages render",
and named Playwright or Dusk/Pest-browser-testing as the candidates,
noting neither was yet in the stack. Chosen: `pestphp/pest-plugin-browser`
(`^4.3`), not raw Playwright or Dusk, because its `LaravelHttpServer`
driver dispatches every browser-originated request through the *same*
in-process application instance the rest of a Pest test runs in (see its
source, `vendor/pestphp/pest-plugin-browser/src/Drivers/
LaravelHttpServer.php`) — including `test()->prepareCookiesForRequest()`,
which carries a plain `$this->actingAs($user)` session into the browser
automatically. That mattered concretely this session: there is no staff
login UI (see the finding below), so the admin verify/approve steps in
the test are ordinary `actingAs()->postJson()` calls, and only the
public-facing steps go through the real browser — both share the same
`RefreshDatabase` transaction because they're the same process. Raw
Playwright or Dusk would have needed a real, separately-authenticated
HTTP session for that bridge, which doesn't exist yet to authenticate
against. This is tooling, not a new architectural pattern — does not
count against the 2-new-technology cap (ABAC, ASVS L2).

Despite being pure-PHP for CDP orchestration, the plugin still launches a
real Chromium via a locally installed `playwright` npm package —
`ext-sockets` and Node.js/npm were added to `docker/Dockerfile` (the dev
image only; `12-session-handoff.md`/`03-architecture.md` still describe
this as separate from Session 8's hardened production image) for this
reason alone. `composer.json`'s `test:e2e` script (`pest tests/Browser`)
runs only that directory, bypassing `phpunit.xml.dist`'s registered
testsuites entirely — `composer test`/the existing `php-quality` CI job
are completely unaffected; a new dedicated `e2e` CI job runs it with
Node available.

## Two real bugs found and fixed while getting the E2E test running (Session 13, 2026-08-18)
Both found by actually running the test repeatedly against a real browser,
not by inspection — worth recording precisely because they're exactly the
kind of environment-specific failure a "the pages render" check would
have missed entirely.

1. **`pestphp/pest-plugin-browser`'s own cleanup code crashes without
   `ext-pcntl`.** `PlaywrightNpmServer::stop()` references the bare
   `SIGTERM` constant unconditionally on non-Windows
   (`vendor/pestphp/pest-plugin-browser/src/Playwright/Servers/
   PlaywrightNpmServer.php:99`), which is only defined when the `pcntl`
   extension is loaded. Without it: every test run's actual assertions
   pass, then the process exits non-zero anyway because teardown throws
   `Undefined constant "...SIGTERM"` — a false failure signal that would
   have made this look broken even though it wasn't. Fixed by adding
   `pcntl` to `docker/Dockerfile`'s `docker-php-ext-install` line and to
   the `e2e` CI job's `shivammathur/setup-php` extensions list. Not a
   project design decision, just a missing runtime dependency of a tool
   this session added — recorded so a future session doesn't waste time
   rediscovering it.
2. **The widget silently failed to mount in a real browser: "process is
   not defined."** `vite.widget.config.js` builds in Vite's library mode
   (needed for a single fixed-filename IIFE — see the decision above).
   Library mode skips Vite's usual app-build default of replacing
   `process.env.NODE_ENV`, which Vue's bundler-targeted build checks at
   runtime; without it, real Chromium throws a `ReferenceError` importing
   Vue, before `resources/js/widget/main.js` ever reaches the line that
   sets `window.PrivacyForgeConsentWidget` — so the widget's own HTML
   shell still rendered (nothing about the *page* failed), but the
   mounted form never appeared. This is exactly why the DoD asked for a
   real browser test rather than "the pages render": a smoke test that
   only checked for static surrounding text on the page passed while the
   actual widget was dead. Fixed by adding `define: {'process.env.NODE_ENV':
   JSON.stringify('production')}` to `vite.widget.config.js` — as a
   side effect, this also let Rollup tree-shake Vue's dev-only warning
   code, shrinking `public/widget.js` from ~105 KB to ~69 KB.

## Finding: no staff login mechanism exists anywhere in this application (Session 13, 2026-08-18)
Discovered while designing the E2E test's admin verify/approve step, not
assumed. `05-api-contracts.md` documents `staffAuth` as session-based
(`web` guard), and every admin-gated controller correctly checks
`$request->user()` — but no controller, route, or view anywhere calls
`Auth::login()` or renders a login form. The *only* place a session is
ever established for a staff user is Pest's `actingAs()` test helper,
which has no HTTP-reachable equivalent. Concretely: today, a real
browser, with real credentials, cannot become an authenticated staff
session at all — every admin JSON endpoint is unreachable to a human
except via a workaround that bypasses the browser (see the README's
step 3, which calls the gated controllers directly via `tinker` rather
than pretending a login flow exists). This is a materially different,
and more fundamental, gap than "richer admin dashboard" in `11-backlog.md`
— that phrasing implies a dashboard is merely undecorated; this means no
staff identity can be established over HTTP at all. Not fixed this
session (out of scope — building a login system is a new feature, not
part of "consent widget + DSAR portal UI"); flagged here and in
`12-session-handoff.md` rather than left for a future session to
rediscover the way Session 11 had to check Session 8's TTL-testing claim.

## ADR-0005 — Single-Organisation Data Model (No Tenant Column)
- **Date:** 2026-08-11 · **Status:** accepted · [Full ADR](../adr/ADR-0005-single-organisation-data-model.md)
- **Decision:** no tenant/org column anywhere in the schema; a
  singleton-constrained settings table instead.
- **Must not be silently reversed because:** adding a dormant tenant column
  "for consistency" would misrepresent this repository's deliberately
  narrow scope and blur the public/private boundary with PR02, which this
  repo's non-goals explicitly guard against.

## Revision: Success Metric #1's wording conflated three different things under one 15-minute number (Session 18, 2026-08-17)

- **Finding.** `00-project-brief.md`'s Success Metric #1 read: "A stranger
  can self-host `privacy-forge` and complete a full consent → withdrawal →
  DSAR → export cycle, starting from the README alone, in under 15
  minutes." Read literally, that single number was being asked to cover
  three genuinely different things at once: (1) a reviewer's experience,
  for whom Session 1 already decided a **public hosted demo instance**
  would exist specifically so most reviewers never clone or build
  anything locally (see this brief's own "Demo/hosting decision" —
  already on record, not a new decision here); (2) a genuine self-hoster's
  **one-time Docker environment build**, which Session 17 measured
  directly at 2083s (~34.7 min) on a real cold clone — more than double
  the budget, on the build alone, before any product interaction starts;
  (3) the **actual product walkthrough** (consent → withdrawal → DSAR →
  export/erasure) once that environment is already running — a
  fundamentally different, much smaller quantity that Session 17 never
  separately measured. Collapsing all three into one number meant the
  metric was simultaneously unverifiable for the reviewer case (no local
  build was ever the point) and, per Session 17's own direct measurement,
  already falsified for the self-hoster case — while the one thing most
  directly within this codebase's control (the product's own UX latency)
  had no honest number of its own to point to.
- **What this session did about the underlying build-time number, before
  revising the metric's wording.** R-07's ~35-minute figure turned out to
  be dominated by `docker/Dockerfile` bundling Node.js, npm, Playwright,
  and a real downloaded Chromium into the *same* image
  `docker-compose.yml`'s `app`/`worker` services build and run by default
  — tooling only `pestphp/pest-plugin-browser`'s `tests/Browser/` suite
  ever uses, never application code at runtime. Split into a multi-stage
  Dockerfile (`runtime` — default, no browser-testing tooling at all —
  and `test` — Node/Playwright/Chromium, built only by a new
  Compose-profile-gated `app-e2e` service). Re-measured on the same class
  of host Session 17 used (Windows 11 + Docker Desktop/WSL2), with the
  identical rigour (`docker compose down`, `docker rmi -f` on all project
  images, `docker builder prune -af`, confirmed 0B cache/0 project images,
  then a single bracketed `docker compose up --build -d`): **643 seconds
  (~10.7 minutes)**, down from 2083s — a genuine ~69% reduction, achieved
  by removing tooling nobody running the plain app ever needed, not by
  loosening the measurement's rigour. This is now *under* budget on its
  own, though the margin (using ~11 of the 15 minutes on environment setup
  alone) is not generous. Full reasoning, including a genuine
  `composer.lock` platform-requirement bug this split surfaced
  (`ext-sockets`/`ext-pcntl` were required by a plain `composer install`
  purely because `pestphp/pest-plugin-browser` is a `require-dev`
  dependency, even though the `runtime` target never has those
  extensions), is in `10-risk-register.md`'s R-07 entry.
- **What this session measured for the product-walkthrough number, and
  what it explicitly could not cleanly measure.** Against the freshly
  rebuilt `runtime` stack, a real, continuous, single bracketed run of
  the README's step-0 bootstrap (`migrate` → `db:seed` →
  `connectors:register-reference` → create consent purpose → create two
  Owner accounts) measured **57 seconds**. Every individual
  product-cycle HTTP call after that (consent grant, DSAR erasure submit,
  admin login × 2, verify-identity, approve-erasure) returned in well
  under a second each, checked directly via curl timing, not assumed.
  This session also attempted to re-time the DSAR's asynchronous
  completion (the worker/reference-connector round trip, previously
  measured cleanly at Session 16 as ~46 real seconds) but the attempt was
  contaminated by this session's own multi-turn tool-call gaps between
  the approve-erasure call and the first completion poll — by the time
  polling started, the job had plausibly already finished in the
  background, so the ~35s this session recorded for that interval is not
  a trustworthy measurement of the async round trip itself and is
  explicitly **discarded, not reported as a real number** — the same
  discipline Session 17 used when it rejected Session 16's own flawed
  ~13-hour build-time anomaly rather than record it as real. Session 16's
  clean ~46-second figure remains the trustworthy number for that specific
  interval.
- **Decision — Success Metric #1's wording is revised** to separate the
  three things the old wording conflated, rather than edited quietly:
  1. **Reviewer experience (public hosted demo):** no local build
     required; this metric's timing does not apply to this path at all —
     it exists so most reviewers never need it to.
  2. **Self-hoster environment setup (one-time):** a real, directly
     measured cold Docker build now taking ~643s (~10.7 min) on a
     representative host, down from a confirmed ~2083s (~34.7 min) before
     this session's Dockerfile split — this number is expected to vary by
     host/network and is not claimed as a universal constant, matching
     Session 17's and 16's own caveats about this figure.
  3. **Product walkthrough (once the environment exists):** the
     consent → withdrawal → DSAR → export/erasure cycle itself. Cleanly
     measured pieces (57s bootstrap; each API call sub-second; ~46s async
     connector completion, Session 16) sum to low single digits of
     minutes of actual backend latency — comfortably under 15 minutes on
     that basis alone. A real human's click-through time (reading each
     README step, typing form values, waiting for page loads) was **not**
     stopwatch-measured against a real browser this session — R-08 (below)
     is exactly why — so "well under 15 minutes" for this specific piece
     is a reasoned characterisation from measured backend latency, not a
     claim this session watched a real person complete in that time.
  See `00-project-brief.md`'s Success Metric #1 for the revised wording
  itself.
- **Not an ADR.** No existing ADR made a commitment this reopens — the
  public-demo decision (Session 1) and the GDPR-only/single-tenant scope
  are both left exactly as they were; this is a correction to one
  success-metric's wording so it asks a question this project can actually
  answer, following the same "correction, not a new architecture
  decision" pattern as the Owner-row fix at Session 10.
