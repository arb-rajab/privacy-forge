# Decision Log
> Purpose: why things are the way they are, so decisions are not silently undone.
> Project: privacy-forge (public)
> Last updated: 2026-08-18 (Session 24)

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

## Forensic finding: "Session 8's hardened production image" does not exist (Session 22, 2026-08-18)

- **Finding.** `docker/Dockerfile`'s own header comment and a
  `09-decision-log.md` Session 13 entry (widget build, above) both refer
  to "the production reference deployment (PHP-FPM + a real web server,
  built at Session 8)" as an established, separate artifact from the dev
  `runtime`/`test` targets. It does not exist: `docker/` contains exactly
  `Dockerfile` (the `deps`/`runtime`/`test-deps`/`test` multi-stage build
  covered by R-07, whose `runtime`/`test` targets both `CMD php artisan
  serve` — Laravel's own docs are explicit this is "not intended for
  production") and `Dockerfile.frontend` (the Vite dev server). No
  PHP-FPM image, no web-server config (nginx/Caddy/Apache), and no
  `docker-compose.prod.yml` or equivalent exist anywhere in the
  repository. This is the same shape of finding as ADR-0008's Laravel
  12.x forensic discovery (Session 20) — a narrated claim and the
  repository's actual state directly contradicting each other, caught
  only because a session that actually needed the artifact went looking
  for it rather than trusting the comment.
- **Why this matters now, specifically.** Part B of this session (public
  demo instance planning, `12-session-handoff.md`) needs to state
  plainly what exists before a real public URL can go live. `php artisan
  serve` is a single-threaded development server; running the actual
  public demo on it would be both a performance and a credibility
  problem for a security-focused portfolio piece. This is now filed as
  `B-06` (`11-backlog.md`) — real, scoped feature work for the dedicated
  infra/hosting session Part B's plan lays out, not something this
  session builds, per Part B's own "planning and scoping only" ground
  rule.
- **Not an ADR, not a reversal of anything.** No ADR ever committed to a
  specific production image design — this is a documentation-vs-reality
  correction, logged here per the same judgement call the Owner-row fix
  (Session 10) and ADR-0008's forensic write-up (Session 20) both used.
  `docker/Dockerfile`'s header comment should be corrected the session
  that actually builds the real production image (`B-06`), not silently
  edited here without also fixing the gap it describes.

## Demo Instance Data Safety: DEMO_MODE wired, `demo:reset` built — groundwork only, not deployed (Session 22, 2026-08-18)

- **Finding.** `DEMO_MODE`/`DEMO_RESET_SCHEDULE` have existed in
  `.env.example` since Session 4 (`06-security-threat-model.md`'s "Demo
  Instance Data Safety" section) with **zero code anywhere reading
  either value** — no `config/demo.php`, no controller check, no
  scheduler entry. A fully documented, fully undocumented-as-unbuilt
  control, the same drift shape as `B-04`.
- **What this session built (Part B groundwork, genuinely low-risk —
  no real infrastructure touched, nothing deployed).**
  1. `config/demo.php` — `enabled`/`reset_schedule`, reading the two
     existing env vars for the first time.
  2. `HandleInertiaRequests::share()` now exposes `demoMode` globally;
     `Welcome.vue` renders the warning banner Demo Instance Data
     Safety's control 4 calls for, gated on it.
  3. `App\Console\Commands\ResetDemoInstanceCommand` (`demo:reset`) —
     control 1 (scheduled reset). Truncates every subject/activity table
     in one statement (Postgres refuses per-table truncation across
     tables with mutual FK references otherwise — see the command's own
     comment) and re-seeds the standard fresh-install baseline
     (`PolicyDefinitionSeeder` + the reference connector). **Refuses to
     run unless `config('demo.enabled')` is true** — the load-bearing
     safety property, since `routes/console.php` registers its scheduler
     entry unconditionally (matching `ExecuteRetentionPoliciesCommand`'s
     existing "registration is unconditional, the command decides"
     shape), so a real self-hosted instance can never have this entry
     silently wipe its data.
  4. `routes/console.php` now schedules `demo:reset` via
     `config('demo.reset_schedule')` (a real cron expression, not
     hardcoded), inert on any instance that hasn't set `DEMO_MODE=true`.
- **What this session deliberately did NOT build, and why — both
  genuine open design questions, not oversights.**
  1. **Control 2 (no persistent shared admin credential / a temporary,
     scoped per-visitor demo identity).** `demo:reset` leaves `users`
     untouched specifically because no such per-visitor identity
     mechanism exists yet — truncating `users` today would just lock
     every visitor out with nothing to replace it. Designing that
     mechanism (a scoped, auto-expiring session identity a demo visitor
     gets without a real login) is real product/security design work,
     not "groundwork," and is filed as `B-08`.
  2. **Richer synthetic demo content.** `demo:reset` resets to the same
     minimal baseline `php artisan db:seed` already produces (five — now
     six, `B-04` — ABAC policies) plus the reference connector, not a
     populated set of sample consent purposes/notices/records a visitor
     could actually explore. Deciding what a compelling, safe demo
     dataset looks like is a content/product decision this session
     wasn't going to rush; filed as `B-07`.
  3. **Control 3 (connector registration disabled entirely on the demo
     build).** No code change was needed: Session 10 already decided
     connector management is CLI-only with no HTTP registration
     endpoint at all, and the only registration command
     (`connectors:register-reference`) registers exclusively the
     reference/stub connector. The control this threat-model item
     describes is already structurally satisfied by that earlier
     decision — recorded here so a future session doesn't rebuild
     something that already exists for a different reason.
  4. **Control 5 (isolation, spend cap, scoped credentials) and TLS.**
     Infrastructure-level, and no hosting target has ever been decided
     (checked `00-project-brief.md`, `03-architecture.md`,
     `01-scope-and-non-goals.md`, `14-maintenance-and-retirement.md` —
     "a public hosted demo instance will exist" is decided; *where* is
     not). See `12-session-handoff.md`'s Part B plan for the
     recommendation and the concrete session breakdown this now needs.
- **Not an ADR.** No ADR ever specified the demo reset's implementation —
  this is new, narrow, within-scope implementation work (a documented
  control finally gaining code), logged here per the same judgement call
  Session 11/12 used for their own non-ADR decisions.

## B-06 re-verified from raw git history before being built (Session 23, 2026-08-18)

- **Why re-verify a finding Session 22 already filed.** Before starting
  Deployment Session A, this session was asked to check whether B-06 was
  merely an unfulfilled *aspirational* comment or whether some prior
  session's own handoff/summary had actually *claimed* the production
  image existed — the latter would be an ADR-0008-shaped problem
  (narration and reality diverging) requiring the same forensic rigor,
  not routine backlog work. This was checked directly against git
  history, not re-derived from Session 22's own account.
- **What the history actually shows.** The "built at Session 8" comment
  was introduced by commit `d2611fa` ("chore(S5): environment, CI
  baseline, and running application skeleton") — **Session 5**, three
  sessions *before* Session 8 ever happened. At the time it was written,
  it was a forward-looking plan ("this will be built at Session 8"), not
  a claim about the present. Session 8 itself (`ae42449`) shipped
  connector dispatch/callback/export-bundle/deletion-certificate work
  (US-007/008/009) — its own handoff document, read in full as part of
  this check, makes zero mention of a production image, PHP-FPM, or a
  web server. No commit ever touches `docker/Dockerfile` between Session
  5 and Session 22 in a way that builds this. Session 13's decision log
  entry, which Session 22 cited as a second source "confirming" the
  claim, does not independently confirm anything — it merely repeats the
  same unverified assumption from the Session 5 comment without checking
  `docker/` itself.
- **Conclusion: this is not the same shape as ADR-0008.** ADR-0008 was a
  real, executed divergence — `composer.json` was actually bumped in the
  same commit whose own prose said the opposite, and every session for
  14 sessions inherited that false-negative belief unchecked. B-06 has no
  such artifact-vs-narration contradiction: no session's own handoff ever
  asserts the production image was built. It is a stale, never-corrected
  *forward-looking* placeholder from Session 5, naming a target session
  that came and went doing unrelated work, silently repeated at Session
  13 without anyone re-checking `docker/`, and finally caught at Session
  22 when a session that actually needed the artifact went looking for
  it. Milder than ADR-0008's shape, but the same root lesson: a claim
  about repository state is not verified state. No new ADR opened; the
  existing B-06 backlog item and Session 22's decision-log entry already
  scoped this correctly as backlog/build work, not a governance reversal.

## Deployment Session A: B-06 built for real, production stack verified locally (Session 23, 2026-08-18)

- **What was built.** `docker/Dockerfile.prod` (two targets: `app` —
  PHP-FPM 8.3, OPcache enabled with production settings, no Node/npm/
  Playwright, no sockets/pcntl since nothing here runs tests/Browser/;
  `web` — Caddy, serves `public/build`'s compiled static assets directly
  and reverse-proxies dynamic requests to `app` over FastCGI), plus
  `docker/Caddyfile` and `docker-compose.prod.yml`.
- **Two real bugs found and fixed while getting it running, not assumed
  away:**
  1. `frontend-assets`'s original `node:20-alpine` base hit npm's own
     documented optional-dependency bug (npm/cli#4828) — `package-lock.json`
     resolved the glibc `@rollup/rollup-linux-x64-gnu` binary (matching
     `docker/Dockerfile.frontend` and CI's `actions/setup-node`, both
     glibc), and Alpine's musl libc has no matching binary. Fixed by
     switching to `node:20-slim`, matching every other Node environment
     already proven to work in this repository.
  2. Caddy's `php_fastcgi app:9000` initially 404'd on every request.
     Root cause: Caddy's own `root` directive governs *its own*
     filesystem (for `file_server`), but the `SCRIPT_FILENAME` FastCGI
     path it sends to the remote `app` container is resolved against
     *that* container's filesystem — `/var/www/html/public`, not Caddy's
     `/srv/public`. Fixed with an explicit `php_fastcgi { root
     /var/www/html/public }` override; confirmed by hitting the 404
     first, not assumed as a known gotcha and pre-emptively avoided.
  3. `route:cache` was deliberately never attempted in the entrypoint:
     `routes/web.php`'s Inertia page routes are closure-based, and
     Laravel's route cache cannot serialize closures. Checked
     `routes/web.php` before writing `docker/entrypoint.prod.sh`, not
     discovered by a crash-looping container. `config:cache` and
     `view:cache` run at container start (not build time — config:cache
     bakes in whatever env is present, and this image is generic across
     deployments with different env per deployment).
- **Verified for real, over real HTTP, against the built images — not
  assumed:**
  1. `docker compose -f docker-compose.prod.yml -p privacy-forge-prod up
     -d --build` — both images build clean.
  2. `GET /up` → `200`, Laravel's real health page, through Caddy → PHP-
     FPM, no dev server anywhere in the path.
  3. `GET /` → `200` (the actual Inertia homepage); a compiled static
     asset (`/build/assets/app-*.css`) → `200` via Caddy's `file_server`.
  4. `docker run --entrypoint sh privacy-forge-app-prod:latest -c "which
     node npm"` → nothing found; `php -m` → no `sockets`/`pcntl`/`xdebug`
     — confirmed the image is actually lean, not just assumed from the
     Dockerfile's own intent.
  5. Real migrations (`php artisan migrate --force`) against this stack's
     own fresh Postgres container, real seeding
     (`PolicyDefinitionSeeder`), a real Owner created
     (`privacy-forge:create-owner`), a real `POST /login` with a genuine
     CSRF/session cookie flow (not `actingAs()`), then a real
     authenticated `GET /api/v1/admin/audit-log` → `200`, returning that
     exact login's own `audit.log.view` audit entry — proving the full
     stack (Caddy, PHP-FPM, Postgres, Redis-backed sessions, the ABAC
     gate) works end to end, the same "prove it over real HTTP" standard
     Session 22 and R-08 established, not just a health-check pass.
- **`docker/Dockerfile`'s and `docker-compose.yml`'s stale "built at
  Session 8" header comments corrected** in the same session that
  finally builds the real thing, per Session 22's own instruction not to
  edit them separately from the fix.
- **What this explicitly does not prove, stated plainly.** Every check
  above ran against `localhost`/local Docker networking. No real cloud
  account, VPS, or hosting credentials exist in this environment (no
  `doctl`/`hcloud`/`flyctl`/`aws`/`az`/`gcloud`/`terraform` CLI is even
  installed) — actual infrastructure provisioning, a real public IP, DNS,
  TLS, and a real spend cap are **not** part of what was verified here
  and were not attempted, matching the ground rule against real cloud
  spend or public exposure without explicit confirmation first. This is
  an honest partial completion of Deployment Session A's own exit
  criterion ("the app responds to `GET /up` over the real
  infrastructure's public IP") — the image half is done and proven; the
  infrastructure half is not, and is handed off explicitly rather than
  silently marked done.
- **B-07/B-08 not touched, correctly per the plan.** Session A's own
  go/no-go checklist only requires them resolved before real go-live
  (Sessions B/C respectively) — they do not block this session's own
  exit criterion and were not designed here.
- **Not an ADR.** No ADR ever specified a production image design; this
  is `B-06` finally being built, per Session 22's own filing of it as
  backlog/build work, not a governance decision.

## Demo/hosting decision revised: real infrastructure explicitly descoped for this portfolio build (Session 24, 2026-08-18)

- **Decision, stated plainly, recorded before any other work this
  session.** Session 1's original decision (`00-project-brief.md`,
  "Demo/hosting decision") committed to a **live, public-facing hosted
  demo instance** — real cloud spend, a real domain, a real box someone
  else could visit. This session revises that: **actually provisioning
  and paying for real public infrastructure is out of scope for this
  portfolio build.** Deployment readiness is instead demonstrated via a
  fully worked local/simulated deployment, run against placeholder
  infrastructure values (a fake domain, self-signed TLS standing in for
  real ACME issuance) — proving the deployment automation and every
  demo-safety control that doesn't itself require real infrastructure,
  without actually paying for or exposing a live box.
- **Why now, and why this is the right call, not a shortcut.** This
  repository's own stated purpose (`00-project-brief.md`'s "why this is
  worth building as a portfolio piece") is demonstrating Requirements
  Analysis and Retirement/Handover/Disposal rigour — not running a
  funded product with an ongoing operations budget. A permanently-live
  public demo instance requires indefinite real cloud spend with no
  revenue behind it, for a marginal credibility gain over a rigorously
  proven local deployment: a reviewer reading this repository's evidence
  (this decision, the verification account below, the working
  automation) can already make the same trust judgement `00-project-
  brief.md`'s own "portfolio context" stakeholder note asks for, without
  this project needing to actually operate a public service indefinitely.
  Session 23 already found, directly, that no cloud account or
  provisioning CLI exists in this environment — this decision resolves
  that blocker by descoping the requirement, rather than by leaving it
  perpetually "blocked, waiting for a human with credentials."
- **What this does not change — the Demo Instance Data Safety CODE
  controls remain real, tested, and are exactly what this session proves
  out.** Scheduled reset (`demo:reset`), connector registration being
  genuinely compiled out (no HTTP registration endpoint anywhere), the
  warning banner (`demoMode` shared Inertia prop), and the `DEMO_MODE`
  flag itself are unchanged as *designs* — this session verifies all four
  work against a real running deployment, it does not weaken or remove
  any of them. Only the fifth control (infrastructure isolation/spend
  cap) and the underlying "is this instance live and public" question are
  affected, and both are marked explicitly not-applicable under this
  decision, not silently dropped — see `06-security-threat-model.md`'s
  updated implementation-status table.
- **What is NOT reopened by this decision.** GDPR/UK-GDPR-only scope,
  single-tenant/no-multi-tenancy (ADR-0005), and every other ADR are
  untouched — this is a revision to exactly one Session 1 business
  assumption (the demo-hosting decision) and its downstream Success
  Metric #5 (`00-project-brief.md`), stated explicitly here rather than
  silently applied, matching the same discipline Session 18 used revising
  Success Metric #1's wording.
- **Effect on the MVP boundary checklist.** `01-scope-and-non-goals.md`'s
  ninth checklist item is revised (not silently marked done) to reflect
  this decision — see that document directly. **This does not, by
  itself, mean v1.0.0 can be tagged** — see `12-session-handoff.md`'s
  Session 24 account for the full assessment against all four conditions
  in that document's own "Definition of v1 complete."

## B-07/B-08 resolved by decision, not deferred again (Session 24, 2026-08-18)

- **Why these were revisited at all.** Both were filed at Session 22 as
  genuine open design questions specifically because a *live public*
  demo instance creates real stakes: richer content matters when a real
  stranger is looking (`B-07`), and a per-visitor scoped identity matters
  because a fixed credential facing the real internet is a real abuse
  vector, T-19 (`B-08`). The decision immediately above removes the "real
  stranger, real internet" premise both of those judgement calls were
  weighing risk/effort against. Re-examining them under the new premise
  is a direct consequence of that decision, not scope creep.
- **B-07 (richer synthetic demo content): resolved by decision, not
  built out.** The minimal baseline `demo:reset` already produces (five
  ABAC policies + the reference connector) is decided as sufficient for
  this project's actual remaining purpose — proving the deployment and
  safety mechanics work, which does not require a rich dataset to
  browse. Building a genuinely compelling sample dataset is downgraded
  from "needed before go-live" (there is no go-live) to ordinary,
  non-blocking backlog polish. See `11-backlog.md`'s "Closed" section.
- **B-08 (per-visitor demo identity): closed, actually built.**
  `App\Console\Commands\ResetDemoInstanceCommand` now truncates `users`
  (previously deliberately left untouched — see Session 22's entry) and
  re-creates exactly one fixed, documented demo-viewer account
  (`config('demo.viewer_email')`/`'viewer_password'`, both configurable
  via `.env.example`'s `DEMO_VIEWER_EMAIL`/`DEMO_VIEWER_PASSWORD`, real
  defaults provided) every time it runs. `users` had to be added to the
  command's existing single-statement `TRUNCATE` list alongside every
  other truncated table, not run separately — `audit_log_entries.
  actor_user_id` and `dsar_requests.identity_verified_by`/
  `erasure_approved_by` both carry live foreign keys into `users`, and
  Postgres refuses to truncate a referenced table unless every
  referencing table is truncated in the same statement (or `CASCADE` is
  used) — checked directly against the migrations before writing the
  change, not discovered by a failing `TRUNCATE`.
  - **Why a single fixed credential is an acceptable simplification of
    the original per-visitor-scoped-identity goal, specifically here:**
    Demo Instance Data Safety control 2 and T-19 exist to prevent a
    shared credential from being discovered and abused by a stranger on
    the public internet. With no live public instance (this session's
    other decision), that specific risk cannot occur — there is no
    public internet this instance is reachable from. This is recorded as
    a conditional simplification, not a permanent design change: if this
    project is ever actually deployed to real public infrastructure, the
    original per-visitor-scoped-identity design becomes the right answer
    again and this decision must be revisited first, not carried forward
    unexamined. `config/demo.php`'s own comment and `06-security-threat-
    model.md`'s T-19 row both say this explicitly, so a future session
    trips over the caveat rather than missing it.
  - **`tests/Feature/ResetDemoInstanceCommandTest.php` updated**
    accordingly: the old assertion that a pre-existing user survives a
    reset ("users are deliberately untouched") is now the opposite —
    a pre-existing user (including a stale demo-viewer row with the
    wrong role/password, simulating a leftover from a differently-shaped
    prior reset) does not survive, and exactly one correctly-configured
    demo-viewer account exists afterward, every time.

## Deployment Session B/C locally verified against placeholder infrastructure values (Session 24, 2026-08-18)

- **What this proves, and how it relates to Session 23.** Session 23
  built and verified `B-06`'s production image against real HTTP,
  entirely over plain `:80`, explicitly stopping short of TLS or a real
  domain because neither existed. This session adds exactly those two
  things — a placeholder domain and TLS — and re-runs the same standard
  of proof (build, migrate, seed, real login, real authenticated API
  call) against them, plus exercises every Demo Instance Data Safety
  control the go/no-go checklist calls for. This is Sessions B and C of
  `08-deployment-and-operations.md`'s original three-session plan,
  collapsed into one session and re-scoped from "against real
  infrastructure" to "against placeholder infrastructure," per this
  session's revised decision above.
- **`docker/Caddyfile`**: now serves `demo.privacy-forge.example` (an
  RFC 2606-reserved, deliberately fake domain — guaranteed to never
  resolve to a real host) with `tls internal`, Caddy's own offline
  local-CA issuance mechanism, substituting for real ACME/Let's Encrypt
  issuance (which needs a real, publicly-resolvable domain and a real
  HTTP-01/DNS-01 challenge — neither exists here). Swapping the site
  address for a real domain and deleting the `tls internal` line is the
  entire diff needed to run this exact config against real
  infrastructure — Caddy's automatic-HTTPS machinery takes over
  immediately, with no other structural change. A second, plain-HTTP
  `:80` block is kept alongside it, unrelated to the TLS site: Caddy's
  automatic-HTTPS redirect is host-matched, so the existing internal
  service-to-service traffic (`REFERENCE_CONNECTOR_BASE_URL=http://web`,
  the reference connector's own webhook callback loop, unchanged since
  Session 23) still reaches this catch-all rather than being redirected
  into a TLS handshake it has no reason to need.
- **`docker-compose.prod.yml`**: `web` now also publishes `8443:443`
  (host-side only — purely a local artifact so this doesn't need a
  privileged host port or collide with anything else already on 443;
  a real deployment would just use 443 directly) and a `caddy-data`
  named volume, so the internal CA and issued certificate survive a
  container restart instead of being re-minted (and re-trusted by
  nothing) every time. `app`/`worker`'s `APP_URL` now reads
  `https://demo.privacy-forge.example`, matching what a real
  deployment's own `.env` would actually contain.
- **A real bug found while proving this, not assumed away:**
  `opcache.validate_timestamps=0` (deliberately set for this production
  image, per its own Dockerfile comment — "correct for an immutable
  production image") means overwriting `bootstrap/cache/config.php` on
  disk (e.g. re-running `php artisan config:cache` with a different
  environment against an already-running container) does **not**
  actually change what already-running PHP-FPM workers observe — they
  keep executing their existing compiled OPcache copy of the old file
  regardless of the new contents on disk. Concretely: setting
  `DEMO_MODE=true` via a one-off `docker compose exec -e DEMO_MODE=true
  ... config:cache` looked like it worked (`demo:reset` ran
  successfully in that same exec session, and `tinker` in a fresh CLI
  process confirmed `config('demo.enabled')` was `true`), but the actual
  HTTP-facing `demoMode` Inertia prop still read `false` — because the
  live HTTP path runs through PHP-FPM's own long-lived worker
  processes, which had already compiled the *previous* `config.php` into
  OPcache before the on-disk file changed, and `validate_timestamps=0`
  means they never re-check. **The correct fix — matching how a real
  operator would actually do this — is to set `DEMO_MODE=true` as a real
  container environment variable and recreate the container**, letting
  `docker/entrypoint.prod.sh`'s own `config:cache` run fresh against the
  right environment from process start, not to patch a running
  container's cache file in place. Verified by doing exactly that
  (`docker compose ... up -d --force-recreate app worker` with
  `DEMO_MODE=true` set as a real service environment variable) and
  re-checking the HTTP-facing prop, which then correctly read `true`.
  This is a genuine operational lesson about this image, worth recording
  precisely because it's the kind of thing that would silently confuse a
  future session (or a real operator) who tries to toggle `DEMO_MODE` on
  a live container without a restart and concludes the flag "doesn't
  work" — it does; it just needs a fresh process, by design, the same
  design intent `Dockerfile.prod`'s own comment already states for any
  config change under `validate_timestamps=0`.
- **Verified for real, over real HTTPS, with a genuinely validated
  certificate chain — not `-k`/insecure-skipped:**
  1. `docker compose -f docker-compose.prod.yml -p privacy-forge-prod up
     -d --build` — both images build clean; `web` reports healthy.
  2. Caddy's internal root CA extracted directly from the running
     container (`/data/caddy/pki/authorities/local/root.crt`) and used
     as `curl --cacert` — i.e. the same trust decision a real client
     makes against a real CA, not a shortcut around it.
  3. `GET /up` over `https://demo.privacy-forge.example:8443` (via
     `curl --resolve`, since no real DNS exists) → `200`, TLS chain
     validated against the extracted CA, leaf certificate's SAN
     confirmed to be exactly `demo.privacy-forge.example`.
  4. A plain HTTP request with `Host: demo.privacy-forge.example`
     against the same container's port 80 → real `308 Permanent
     Redirect` to the HTTPS URL — Caddy's automatic-HTTPS redirect,
     live and working, the same mechanism that would fire against a
     real domain.
  5. Real migrations, real `demo:reset` (ABAC policies + reference
     connector re-seeded, demo-viewer account created), a real `POST
     /login` over HTTPS with a genuine session/CSRF cookie flow (GET `/`
     for the session + `XSRF-TOKEN` cookie, decoded and sent back as
     `X-XSRF-TOKEN`, matching the same standard Session 23 used for its
     own plain-HTTP login) using the fixed demo-viewer credentials, then
     a real authenticated `GET /api/v1/admin/audit-log` → `200`,
     returning that exact login's own `audit.log.view` audit entry —
     the complete stack (Caddy TLS termination, PHP-FPM, Postgres,
     Redis-backed sessions, the ABAC gate) proven end to end over HTTPS,
     the same rigour Session 22/23 and R-08 established.
  6. `demo:reset`'s actual reset behaviour re-verified against real data,
     not just "the command exits 0": a consent purpose created via
     `tinker` (simulating a visitor's activity) existed before a reset
     and was confirmed gone after it.
  7. Connector registration re-confirmed genuinely compiled out on the
     *running* production image itself (`php artisan route:list` inside
     the live container), not only reasoned about from source — exactly
     two connector-related routes exist (the reference connector's own
     webhook receiver, and the generic connector-callback endpoint), no
     registration endpoint anywhere.
- **Go/no-go checklist (`08-deployment-and-operations.md`) exercised
  against this local, placeholder-backed deployment, item by item:**
  `DEMO_MODE=true` (real container env, confirmed via the live
  `demoMode` prop); connector registration compiled out (confirmed
  above); a real spend cap and infrastructure isolation — explicitly
  marked **not applicable**, per this session's descoping decision, not
  silently skipped; `demo:reset` scheduled and proven to actually run
  and actually reset state (confirmed above; still not run by a real
  cron scheduler against a long-lived instance, since none exists, only
  invoked directly — an honest, stated limit); `B-08` resolved (fixed
  demo-viewer credential); `B-07` resolved by decision (minimal baseline
  is sufficient).
- **Not an ADR.** No ADR ever specified TLS or the demo-hosting design;
  this is `08-deployment-and-operations.md`'s own Session B/C plan being
  executed, re-scoped by the decision above, not a governance reversal.

## R-01 closed for real: a genuinely separate runtime role, not owner self-revoke; hash-chain locking changed to an advisory lock (Session 27, 2026-08-19)

- **Decision:** R-01 (`10-risk-register.md`) is closed by creating a
  second Postgres role, `privacy_forge_app`, that does **not** own the
  `audit_log_entries` table (or any other table) and is granted only
  `SELECT`/`INSERT` on it (full `SELECT`/`INSERT`/`UPDATE`/`DELETE`
  elsewhere, matching what it always needed). The schema-owning role
  (`privacy_forge`, unchanged) is now used only for `php artisan migrate
  --database=pgsql_migrate`; the running application (`app`/`worker` in
  both compose files) connects as `privacy_forge_app` for everything
  else. Implemented in
  `database/migrations/2026_08_19_000001_add_restricted_runtime_role_for_audit_log.php`,
  wired via `config/database.php`'s new `pgsql_migrate` connection.
- **Alternative considered and empirically rejected: the owning role
  revoking `UPDATE`/`DELETE` from itself.** ADR-0003's original text
  assumed this was impossible ("Postgres doesn't allow an owner to
  revoke privileges from itself") — tested directly against a real
  Postgres 16 instance and found **half wrong**: an owner *can* run
  `REVOKE UPDATE, DELETE ON t FROM owner_role`, and Postgres genuinely
  enforces it against subsequent `UPDATE`/`DELETE` statements. But the
  same owner role can just as trivially `GRANT` the privilege back to
  itself afterward — ownership carries the right to alter a table's ACL
  regardless of the ACL's current contents, and that specific right
  cannot itself be revoked short of `ALTER TABLE ... OWNER TO`. Verified
  end to end: revoke, confirm `UPDATE` fails, `GRANT` it back, confirm
  the same connection now updates the row it "couldn't" a moment
  earlier. Against R-01's actual threat model — the app's own runtime
  DB credential running attacker-controlled or buggy arbitrary SQL — a
  self-revoke is only a soft barrier: the same SQL access that could
  tamper with a row could just as easily re-grant itself the privilege
  first. A role that never owned the table and holds no grant option
  cannot do this — `GRANT` requires ownership, superuser, or an existing
  grant option, none of which it has. This is a correction to ADR-0003's
  stated premise, not a reversal of its Decision (Option A + B); ADR-0003
  itself is not reopened (per this session's ground rules) — recorded
  here since the premise, not the decision, was what needed fixing.
- **Real correctness issue found and fixed as a direct consequence:**
  Postgres requires the `UPDATE` privilege for `SELECT ... FOR UPDATE`
  **and** `SELECT ... FOR SHARE`, even though neither issues an actual
  `UPDATE` — verified directly (a role granted only `SELECT`/`INSERT`
  gets `permission denied` on both). `AuditLogger::record()` used
  `->lockForUpdate()` to serialize concurrent hash-chain writes, which
  would have broken outright under the new restricted role — a role
  that can never legitimately need `UPDATE` to write the log correctly
  would otherwise be unable to insert into it at all. Fixed by replacing
  the row lock with `pg_advisory_xact_lock(hashtext(...))`: an advisory
  lock needs no table privilege of any kind, and still serializes the
  "read last hash → compute next hash → insert" critical section the
  same way the row lock did. **Not an ADR:** this is an implementation
  detail of ADR-0003's existing hash-chain mechanism (how concurrent
  writers are serialized), not a change to the tamper-evidence design
  itself — logged here per the same judgement call Session 7 made for
  cross-field vs. fail-closed documentation-only decisions.
- **A second, test-only interaction found and fixed:** tests that
  either (a) invoke `demo:reset` (whose `TRUNCATE` must now run via the
  owning `pgsql_migrate` connection, since the runtime role deliberately
  has no `TRUNCATE` on `audit_log_entries`) or (b) simulate direct-DB-
  access tampering by writing to `audit_log_entries` via that same owning
  connection, do so from a **second, genuinely separate Postgres
  session** — not just a different Laravel connection name. Two real,
  verified consequences of that: (1) `RefreshDatabase` holds the whole
  test in one open transaction on the default connection, so a `TRUNCATE`
  issued from the other session blocks on that transaction's locks
  forever — a real deadlock, reproduced and confirmed via
  `pg_stat_activity` (one session `idle in transaction`, the other
  `active`/`Lock`/`relation`), not a flaky timeout. (2) Rows inserted-but-
  not-committed on the default connection are genuinely invisible to the
  other session — verified directly via `tinker` — so a same-test
  cross-connection write against them silently matches zero rows rather
  than erroring. Both are fixed the same way: an explicit `DB::commit()`
  before crossing to the other connection, in
  `tests/Feature/ResetDemoInstanceCommandTest.php`,
  `tests/Feature/AuditChainAnchorTest.php`, and
  `tests/Feature/ConsentCaptureTest.php` — each commented with why. This
  only affects the test harness: real usage never holds an explicit
  transaction open across either boundary (a scheduled `demo:reset` runs
  in its own process; a real attacker with direct DB access is acting on
  already-committed rows).
- **Proof, not just design:**
  `tests/Feature/AuditLogGrantEnforcementTest.php` connects as the real
  app runtime role (confirmed via `current_user`, distinct from the
  migrate role) and issues raw SQL `UPDATE`/`DELETE` against
  `audit_log_entries` — not through `AuditLogEntry::save()`/`delete()`,
  which already throw at the application layer and would prove nothing
  about the database itself. Both are rejected with Postgres error
  `42501` (`insufficient_privilege`); `SELECT`/`INSERT` still work
  (positive control). Independently reproduced with a raw `psql` session
  as `privacy_forge_app` against both `docker-compose.yml` and
  `docker-compose.prod.yml`'s Postgres, and end to end against the
  running prod-shape stack: real migration run
  (`--database=pgsql_migrate`) against its *existing* data volume (not
  just a fresh one — the role-creation migration is idempotent, checked
  via `pg_roles`), a real `privacy-forge:create-owner`, a real `POST
  /login` over HTTPS, and a real authenticated `GET
  /api/v1/admin/audit-log` returning that login's own audit entries —
  the app runtime role's `SELECT`/`INSERT` path fully working, same
  standard as every other verified claim in this project.
