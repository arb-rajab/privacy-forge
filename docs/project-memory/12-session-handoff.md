# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 22 — Close B-04/B-05 (audit log
  endpoint + retention execution history), then plan (not build) the
  public demo instance deployment.**
- Objective: Part A closed two small, well-scoped backend gaps found
  while building Session 21's admin UI. Part B read the demo-instance
  design (decided since Session 1, detailed since Session 4) and
  produced a concrete session plan for actually deploying it, doing only
  genuinely low-risk groundwork rather than provisioning real
  infrastructure.
- Status: **both Part A items closed, real and tested, wired into the
  UI. Part B produced a concrete plan (`08-deployment-and-operations.md`,
  now real content instead of an empty template) plus a small amount of
  safe groundwork (see below) — no real infrastructure was touched, no
  money was spent, and a genuine forensic finding (B-06) was surfaced
  along the way.** All 186 Feature tests pass (165 pre-existing + 21
  new), Pint/Larastan (level 8)/ESLint clean, OpenAPI validates.

## Part A — B-04 and B-05

### 1. B-04: `GET /admin/audit-log` — built for real
- **New sensitive action: `audit.log.view`** (the sixth registered ABAC
  action). Owner or Privacy Manager may reach the endpoint at all
  (Support Staff denied, per the roles matrix) —
  `database/seeders/PolicyDefinitionSeeder.php`,
  `database/factories/PolicyDefinitionFactory.php::forAuditLogView()`.
- **Gating decision, made explicitly rather than left implicit** (per
  this session's own brief, "decide and state which"): the roles matrix
  names two *different* scopes for this capability — Owner "view full
  audit log," Privacy Manager "view audit log entries related to their
  actions." `PolicyEvaluator`'s ABAC conditions decide *whether* a
  request is allowed at all; they do not filter *which rows* a list
  endpoint returns — no existing sensitive action needed that before
  now. So the row-level scope is applied in
  `App\Http\Controllers\Admin\AuditLogController::index()` itself, after
  the ABAC gate allows the request: an Owner's query is unfiltered; a
  Privacy Manager's query is additionally scoped to
  `actor_user_id = $actor->id`. This is a controller-level query
  decision, not a new `PolicyEvaluator` condition type — see that
  controller's class comment for the full reasoning on why stretching
  ABAC conditions to do row-level filtering would be a real engine
  change, out of scope by this session's own ground rules (no ADR
  reopened).
- Filters: `resourceType`, `resourceId` (already spec'd, Session 3),
  `since`/`until` (new — the task brief asked for date-range filtering
  the existing spec didn't yet have; added to `openapi.yaml` with
  `date` validation, 422 on a malformed value).
- `App\Http\Resources\AuditLogEntryResource` — shape matches
  `openapi.yaml`'s existing `AuditLogEntry` schema exactly.
- **UI**: new `resources/js/Pages/AdminAuditLog.vue` at `/admin/audit-log`
  — filter form (resource type/id, since/until), a table of results, and
  an explicit note that what a viewer sees depends on their role,
  decided server-side, not filtered client-side. Linked from Welcome.vue
  and every other admin page's nav bar.

### 2. B-05: retention execution history — built for real
- **No new sensitive action** — `GET /admin/retention-policies/{id}/executions`
  shares the existing `retention.policy.manage` gate, the same
  "index/show/dry-run share one gate" reasoning
  `RetentionPolicyController`'s own class comment already established.
  New method: `RetentionPolicyController::executions()`.
- `App\Http\Resources\RetentionExecutionResource` — `certificate` reuses
  the existing `DeletionCertificateSummary` schema (already used by
  `DsarStatus.deletion_certificate`) rather than inventing a second
  certificate shape; `null` for a dry run, present for a real execution.
- `openapi.yaml`: new path + `RetentionExecution` schema.
- **UI**: `AdminRetention.vue`'s "Past execution history" section — an
  honest placeholder since Session 21 — now has a real policy-picker
  dropdown and a real table (mode, affected count, executed-at,
  certificate summary/exceptions), calling the new endpoint.

### Both items, exactly as specified in this session's own ground rules
- Real Feature tests: `tests/Feature/AuditLogQueryTest.php` (8 tests —
  full-vs-scoped visibility, filters, chain ordering, fail-closed),
  `tests/Feature/RetentionExecutionHistoryTest.php` (4 tests). Both
  actions' allow/deny cells added to
  `tests/Feature/AuthorisationMatrixTest.php`'s exhaustive
  (role × action) dataset — six registered sensitive actions now, not
  five; the file's own "not applicable yet" placeholder test for audit
  log access was removed (it moved into the real dataset, the same way
  `retention.policy.manage` did at Session 11).
- Pint clean (157→161 files, all clean), Larastan level 8 clean (0
  errors, 68 files), ESLint clean, `npm run build` succeeds.
- `openapi.yaml` updated for both (neither was previously unspecified —
  B-04's path already existed since Session 3 and only needed the new
  query params + the gating note; B-05's path and schema were net-new)
  and re-validated with the real `openapi-spec-validator` tool (same
  throwaway-container method Session 21 used): **`/spec/openapi.yaml:
  OK`**.

## Part B — Public demo instance: planned, and a small amount of real (safe) groundwork

### What was read first, per this session's own instructions
- `06-security-threat-model.md`'s "Demo Instance Data Safety" section in
  full — five controls, designed Session 4, none implemented before this
  session.
- `08-deployment-and-operations.md` — confirmed it was the empty
  template (matching `14-maintenance-and-retirement.md`'s state before
  Session 19, as the brief predicted).
- `00-project-brief.md`, `03-architecture.md`, `01-scope-and-non-goals.md`,
  `14-maintenance-and-retirement.md` — checked directly for a decided
  hosting target. **None exists.** "A public hosted demo instance will
  exist" is decided (Session 1); *where* it runs never was. Stated
  plainly in `08-deployment-and-operations.md` rather than assumed one
  way or the other.

### A genuine forensic finding, surfaced while checking what already exists (B-06)
`docker/Dockerfile`'s own header comment and a Session 13 decision-log
entry both describe "the production reference deployment (PHP-FPM + a
real web server, built at Session 8)" as an existing artifact separate
from the dev image. **It does not exist.** `docker/` contains only the
dev/CI `Dockerfile` (`runtime`/`test` targets, both `CMD php artisan
serve` — a single-threaded dev server) and `Dockerfile.frontend` (Vite
dev server). This is the same shape of finding as ADR-0008's Laravel
12.x forensic discovery (Session 20): a narrated claim and the
repository's actual state directly contradict each other. Filed as
`B-06` — a hard blocker for the demo going live on a real URL, since
`php artisan serve` is not production-appropriate. See
`09-decision-log.md`'s Session 22 entry for the full account.

### Groundwork actually built this session (real code, real tests, zero real infrastructure touched)
1. **`config/demo.php`** — `DEMO_MODE`/`DEMO_RESET_SCHEDULE` have existed
   in `.env.example` since Session 4 with **zero code anywhere reading
   either value**. This is the first code that does.
2. **The warning banner (control 4).** `HandleInertiaRequests::share()`
   now exposes `demoMode` globally; `Welcome.vue` renders the banner
   when it's true. Tested (`tests/Feature/DemoModeSharedPropTest.php`).
3. **`php artisan demo:reset`** (control 1 — scheduled reset,
   `App\Console\Commands\ResetDemoInstanceCommand`). Truncates every
   subject/activity table in one statement (Postgres requires this —
   see the command's own comment on why per-table truncation across
   mutually-referencing tables fails) and re-seeds the standard
   fresh-install baseline. **Refuses to run unless
   `config('demo.enabled')` is true** — routes/console.php registers its
   scheduler entry unconditionally, so this refusal is what keeps a real
   self-hosted instance safe from ever having this entry do anything.
   Tested (`tests/Feature/ResetDemoInstanceCommandTest.php`, 3 tests —
   including that it's a genuine no-op when disabled, and that the
   audit chain sequence restarts at genesis after a real reset).
4. **`routes/console.php`** schedules `demo:reset` via the configurable
   cron expression, inert on any non-demo instance.
5. **Control 3 (connector registration disabled) needed no code change**
   — already structurally satisfied by Session 10's unrelated decision
   that connector management is CLI-only with exactly one registration
   command (the reference/stub connector). Recorded in
   `09-decision-log.md` so a future session doesn't rebuild something
   that already exists for a different reason.

### What was deliberately NOT built, and why (both real open design questions, not oversights)
1. **Control 2 — no persistent shared admin credential / a scoped
   per-visitor demo identity.** `demo:reset` leaves `users` untouched
   specifically because no such mechanism exists yet — truncating it
   today would lock out every visitor with nothing to replace it.
   Designing this (how a visitor gets a session without a real login or
   a long-lived shared credential) is real security design work, filed
   as `B-08`, not attempted this session.
2. **Richer synthetic demo content.** `demo:reset` resets to the
   existing minimal baseline (six ABAC policies + the reference
   connector), not a populated, explorable demo dataset. Filed as `B-07`
   — a content/product decision, not groundwork.
3. **Control 5 (isolation, spend cap, scoped credentials) and TLS.**
   Purely infrastructure, blocked on the undecided hosting target — not
   attempted, per this session's explicit "planning only" scope for
   anything touching real infrastructure or money.
4. **No real infrastructure was provisioned. No cloud account was
   touched. No money was spent.** Every piece of Part B's code above was
   validated only against this session's local dev/CI containers.

### The plan itself
`docs/project-memory/08-deployment-and-operations.md` now has real
content in every section instead of empty headers, plus a dedicated "The
actual deployment session(s)" section: a hosting recommendation (a
single small VPS running `docker compose` directly, with reasoning and
an explicit counter-consideration), the concrete go/no-go checklist for
a real public URL, and a three-session breakdown (A: production image +
infra provisioning; B: demo-safety verification + DNS/TLS; C: go-live
checklist + the B-07 content decision), each with an independently
checkable exit criterion.

## MVP boundary checklist — honest current count

**Unchanged at 8 of 9, exactly as Session 21 left it — this session's
work does not move the checklist**, because the checklist's own ninth
item asks for "a public demo instance running on synthetic seed data, in
isolated infrastructure, with a spend cap" (`01-scope-and-non-goals.md`)
— an actually-deployed instance, not a plan for one plus some
groundwork. **What has changed is the *shape* of what's left**: it is no
longer a vague "public demo instance" line item — it is now the
three-session plan above, with a named hosting decision to make, a named
hard blocker (`B-06`, no production image exists), and two named open
design questions (`B-07` content, `B-08` visitor identity) that must be
resolved before it. The next session has a checklist, not a restart.

**Is "credible v1" per that file's Definition of "v1 complete" met now?**
No, unchanged from Session 21 — condition 1 requires every box checked,
and the demo-instance box is still unchecked. This session did not
change that answer and was never going to; Part B was explicitly
planning, not execution.

## Files created or changed

**New (Part A):**
- `app/Http/Controllers/Admin/AuditLogController.php`
- `app/Http/Resources/AuditLogEntryResource.php`
- `app/Http/Resources/RetentionExecutionResource.php`
- `resources/js/Pages/AdminAuditLog.vue`
- `tests/Feature/AuditLogQueryTest.php`
- `tests/Feature/RetentionExecutionHistoryTest.php`

**New (Part B groundwork):**
- `config/demo.php`
- `app/Console/Commands/ResetDemoInstanceCommand.php`
- `tests/Feature/ResetDemoInstanceCommandTest.php`
- `tests/Feature/DemoModeSharedPropTest.php`

**Changed:**
- `app/Http/Controllers/Admin/RetentionPolicyController.php` — new
  `executions()` method, sharing the existing gate.
- `routes/api.php` — two new GET routes (`/audit-log`,
  `/retention-policies/{id}/executions`), both under the existing
  `['web','auth']` admin group.
- `routes/console.php` — `demo:reset` scheduled via
  `config('demo.reset_schedule')`.
- `database/seeders/PolicyDefinitionSeeder.php`,
  `database/factories/PolicyDefinitionFactory.php` — `audit.log.view`
  added.
- `app/Http/Middleware/HandleInertiaRequests.php` — `demoMode` shared
  prop.
- `resources/js/Pages/Welcome.vue` — demo warning banner + audit-log nav
  link.
- `resources/js/Pages/AdminRetention.vue` — real execution history
  section, replacing the honest placeholder.
- `resources/js/Pages/AdminRopa.vue`, `AdminPolicies.vue` — audit-log nav
  link added for consistency.
- `tests/Feature/AuthorisationMatrixTest.php` — `audit.log.view` added
  to the exhaustive matrix; the stale "not applicable yet" test removed.
- `docs/architecture/openapi.yaml` — `/admin/audit-log`'s query params
  and gating note; new `/admin/retention-policies/{id}/executions` path
  and `RetentionExecution` schema.
- `docs/project-memory/06-security-threat-model.md` — an "Implementation
  status (Session 22)" table added under Demo Instance Data Safety (the
  controls themselves are unchanged; this only records what code exists
  today).
- `docs/project-memory/08-deployment-and-operations.md` — full rewrite
  from the empty template (this session's main Part B deliverable).
- `docs/project-memory/09-decision-log.md` — two new entries (the B-06
  forensic finding; the DEMO_MODE/`demo:reset` groundwork decisions).
- `docs/project-memory/11-backlog.md` — three new entries (`B-06`,
  `B-07`, `B-08`).
- `docs/project-memory/12-session-handoff.md` (this file).

**Not changed:** any ADR (ADR-0001 through ADR-0008 — none reopened, per
this session's ground rules), `01-scope-and-non-goals.md`'s GDPR-only/
single-tenant/public-demo decisions (read, not reopened),
`composer.json`/`composer.lock`/`package.json` (no new dependencies).

## Validation performed

- **`composer test` (Pest) → 186/186 passed, 750 assertions, 87.9s** —
  run repeatedly through this session as work progressed, not just once
  at the end.
- **`composer lint` (Pint) → clean, 161 files.**
- **`composer analyse` (Larastan, level 8) → 0 errors, 68 files.**
- **`npm run lint` (ESLint) → clean.**
- **`npm run build` → succeeds** (both the main app bundle and the
  standalone widget build).
- **`docs/architecture/openapi.yaml` → valid**, checked with the real
  `openapi-spec-validator` tool via the same throwaway
  `python:3.12-slim`-container method Session 21 used.
- **Manual walkthrough of B-04/B-05 over real HTTP, against the actual
  running docker-compose stack** — the same substitute pattern R-08
  established and Session 21 followed:
  1. Seeded a fresh dev database (`db:seed`, `privacy-forge:create-owner`
     for a real Owner) and logged in for real via `POST /login` (real
     session cookie, real per-request CSRF token from the rendered
     Inertia page — not `actingAs()`).
  2. `GET /admin/audit-log` → `200`, Inertia page renders as
     `AdminAuditLog`. `GET /api/v1/admin/audit-log` → `200`, returned
     this very request's own `audit.log.view` allow entry — proving the
     endpoint, the gate, and the audit trail it writes to are all real,
     not mocked.
  3. Created a real data category and retention policy, ran a real
     dry-run (`POST .../dry-run` → `200`, `affected_record_count: 0`),
     then `GET .../executions` → `200`, returned that exact execution
     row with `mode: dry_run`, `certificate: null` — proving B-05's
     endpoint reflects real `RetentionExecution` rows, not fixture data.
  4. Created a real Privacy Manager and a real Support Staff account.
     Logged in as the Privacy Manager: `GET /api/v1/admin/audit-log` →
     `200`, returned **only** that manager's own `audit.log.view` entry
     — not the Owner's earlier category/policy/dry-run actions already
     in the same log — directly proving the row-level scoping decision
     documented above, over real HTTP, not just in a Pest test. Logged
     in as Support Staff: both `GET /api/v1/admin/audit-log` and
     `GET .../executions` → real `403`s with the correct `policy_id`.
  5. Exercised `demo:reset` for real via CLI: `DEMO_MODE=true php
     artisan demo:reset` → succeeds, re-seeds six policies and one
     connector; the same command with `DEMO_MODE` unset → refuses,
     exit code 1, touches nothing. (The dev database was independently
     wiped between these two steps by an intervening `composer test`
     run — this project's `phpunit.xml.dist` deliberately points tests
     at the same Postgres connection as dev, and `RefreshDatabase` runs
     `migrate:fresh` on its first test — not a bug in `demo:reset`
     itself; the isolated Pest tests in
     `ResetDemoInstanceCommandTest.php` are the rigorous evidence for
     its behaviour, this CLI run is the "it really executes for real"
     confirmation.) The dev environment was restored to a working state
     (migrated, seeded, reference connector registered, a fresh Owner
     account created) before ending the session, so it isn't left broken
     for whoever opens this repository next.
  **Stated plainly, matching R-08's own language:** this walkthrough
  proves the exact backend contract every new button/endpoint exposes,
  over real HTTP/CLI, with real sessions and real data. It does not
  prove a real mouse click in a real rendered browser fires the new
  `AdminAuditLog.vue`/`AdminRetention.vue` history section's `fetch()`
  calls — that gap is the same accepted shape as every other
  admin-dashboard page since Session 14 (R-08), not newly introduced or
  newly claimed closed here.

## What was explicitly NOT done this session, and why

1. **No real infrastructure provisioned, no cloud account touched, no
   money spent** — Part B's explicit scope was planning plus
   low-risk-only groundwork; both boundaries were respected throughout.
2. **`B-06`'s real production image was not built** — found, filed, and
   named as a hard blocker in the deployment plan, not fixed this
   session (it's real, non-trivial feature work, exactly the kind Part B
   was scoped to plan around, not rush into the same session as Part A).
3. **`B-07` (demo content) and `B-08` (visitor identity) were not
   designed, only named** — both are genuine open design questions this
   session judged as needing their own real design pass, not a rushed
   implementation bolted onto Part B's groundwork.
4. **No ADR reopened.** GDPR-only, single-tenant, and the public-demo
   decision itself were read, not re-decided, per this session's own
   ground rules.
5. **`docker/Dockerfile`'s misleading "built at Session 8" comment was
   left as-is**, deliberately — see `09-decision-log.md`'s B-06 entry:
   fixing just the comment without building the real image it describes
   would misrepresent progress; the session that builds `B-06` should
   correct it alongside the real fix.
6. **B-01, B-02, B-03 (prior sessions' backlog items) were not picked
   up** — out of this session's scope; still open, unchanged.

## Open questions and risks

- **R-01 through R-08 — not touched, none affected as a side effect.**
  This session's only backend interaction beyond Parts A/B's own new
  code was reading existing controllers/config and calling
  existing/new endpoints over real HTTP/CLI for validation; no
  unrelated application code changed. Specifically:
  - **R-07's rate-limit follow-up trigger (2026-08-24) is not yet
    due** — today is 2026-08-18 — so per `10-risk-register.md`'s own
    dated instruction, no re-check was performed this session. The next
    session that touches this repository on or after 2026-08-24 should
    run it.
  - **R-08 — unchanged, still accepted residual risk.** This session's
    manual walkthrough follows the exact same curl/CLI substitute
    pattern the risk register already accepts, adding B-04/B-05/demo:reset
    coverage to it without claiming to have closed the underlying
    browser-automation gap.
- **B-01, B-02, B-03 — unchanged, still open.**
- **B-04, B-05 — closed this session.**
- **B-06 (new) — no production-grade application image exists**,
  despite a comment claiming otherwise. Hard blocker for the demo going
  live; first item in the recommended Session A above.
- **B-07 (new) — demo synthetic content is undesigned** beyond the
  minimal ABAC/connector baseline. Needed for a *compelling* demo, not a
  safe one.
- **B-08 (new) — no scoped per-visitor demo identity mechanism is
  designed.** A real open security-design question, not a detail;
  needed for anyone to actually log in to the demo without either a
  shared credential (the exact thing control 2 exists to avoid) or no
  staff-side demo at all.
- **MVP boundary — still 8 of 9**, unchanged by this session (see
  dedicated section above) — the shape of the ninth item is now concrete
  (three planned sessions, a named blocker, two named design questions),
  not vague.

## Next recommended session

**Single objective: Session A of the three-session plan in
`08-deployment-and-operations.md` — build the real production image
(`B-06`) and provision the actual hosting infrastructure**, confirming
or overriding this session's VPS-with-`docker-compose` recommendation
first. Exit criterion stated in the plan: the app responds to `GET /up`
over real infrastructure's public IP (no DNS/TLS yet — that's Session B).

Do NOT attempt Session B or C's scope in the same session as Session A,
for the same reason this session didn't rush Part A and Part B together
— infra provisioning and demo-safety verification are different failure
modes that deserve to be checked independently.

- Inputs required: `docs/project-memory/08-deployment-and-operations.md`
  (this session's plan, read in full — not just the checklist), `docs/
  project-memory/09-decision-log.md`'s two Session 22 entries (the B-06
  forensic finding; the DEMO_MODE/`demo:reset` reasoning),
  `docs/project-memory/11-backlog.md` (B-06/B-07/B-08),
  `docker/Dockerfile` (what exists today, to build the real image from,
  not replace wholesale), `config/demo.php` and
  `app/Console/Commands/ResetDemoInstanceCommand.php` (what already
  exists and just needs to actually run somewhere real).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 22
complete.

**Current stack:** unchanged — no dependency versions touched this
session. PHP 8.3, Vue 3/Inertia, PostgreSQL 16, Redis 7, S3-compatible
storage. No new dependencies.

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0-21 remain in force. Nothing about the stack, any ADR, or the
GDPR-only/single-tenant/public-demo decisions was touched this session.

**Implementation state:**
- Done: everything from Session 21, plus: `GET /admin/audit-log` (real
  endpoint, real UI, real role-scoped visibility), `GET
  /admin/retention-policies/{id}/executions` (real endpoint, real UI);
  `DEMO_MODE`/`demo:reset`/the warning banner all wired for the first
  time (code exists, never run against a real deployment); a concrete,
  three-session deployment plan with a hosting recommendation.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) `B-06` — no production-grade
  application image exists, despite a stale comment claiming otherwise
  — the actual hard blocker for going live; (2) `B-07` — demo content
  undesigned; (3) `B-08` — demo visitor identity undesigned; (4) R-01 —
  still open, DB-level grant revocation for the audit log unbuilt; (5)
  R-07's rate-limit follow-up — re-check due 2026-08-24, not before;
  (6) R-08 — accepted residual, unchanged; (7) B-01/B-02/B-03 —
  unchanged, still open.
- Not started: unchanged from Session 21's list, plus now B-06/B-07/B-08,
  plus real infra provisioning, TLS, and a verified spend cap for the
  demo instance.

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — this session introduced no new
architectural pattern or dependency.

**Task for next session (single objective):** Session A of the
deployment plan — build the real production image (`B-06`) and get it
running on real (or overridden-recommendation) infrastructure, reachable
by IP. Do not attempt DNS/TLS/demo-safety-verification (Session B) or
the go-live checklist (Session C) in the same session.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/08-deployment-and-operations.md` (the plan itself
  — read the "actual deployment session(s)" section in full)
- `docs/project-memory/09-decision-log.md`'s two Session 22 entries
- `docs/project-memory/11-backlog.md` (B-06, B-07, B-08 new this session)
- `docker/Dockerfile` (the existing dev/CI image to build the real one
  alongside, not replace)

**Ground rules:** Do not change the stack. Do not reopen any ADR
(ADR-0001 through ADR-0008). Do not reopen GDPR-only/single-tenant/
public-demo — those are decided; Session A is about executing what was
already decided (the demo instance will exist somewhere), not
re-deciding whether it should. Do not spend real money or provision real
infrastructure without confirming the hosting choice first — this
session's VPS recommendation is a starting point, not a mandate. R-01
remains open; R-07's follow-up isn't due until 2026-08-24; R-08 is
accepted residual — don't reopen any of them without a genuine new
finding.
