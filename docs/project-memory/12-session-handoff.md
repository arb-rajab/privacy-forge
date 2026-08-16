# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 13 — Part A: risk-register
  corrections (R-02 re-scope, R-04 added); Part B: embeddable consent
  widget + public DSAR portal UI (closes Success Metric #1)**
- Objective: (A) verify R-02's seeder gap covers the current 5 registered
  policies (not just the original 1) and correct its wording; add R-04 to
  track ADR-0003's unimplemented external chain-anchor. (B) build the
  last MVP-boundary UI gap — an embeddable consent widget and a public
  DSAR intake/status portal — and prove the actual consent → withdrawal →
  DSAR → export/erasure cycle end-to-end in a real browser, not just "the
  pages render."
- Status: **complete, not yet pushed** — 150/150 pre-existing tests still
  passing (no regressions, no new backend tests — this session added no
  new PHP business logic beyond two Vue pages' worth of routes), plus a
  new real end-to-end browser test (`tests/Browser/DsarLifecycleTest.php`,
  1 test/14 assertions) passing reliably across repeated runs.
  `vendor/bin/pint --test` clean (140 files), `vendor/bin/phpstan analyse`
  (Larastan level 8) clean, `npm run lint` clean, `npm run build` clean
  (both the main app bundle and the new standalone widget bundle),
  `docs/architecture/openapi.yaml` re-validated (`openapi_spec_validator`,
  throwaway `python:3.12-slim` container — OK). No new migrations this
  session, so no rollback-parity check applies.

## Part A — risk-register corrections

1. **R-02 re-verified, not just re-asserted.** Confirmed this session,
   directly: `database/seeders/` is an empty, untracked directory (`git
   ls-files database/seeders` returns nothing, no `DatabaseSeeder.php`
   anywhere), and the only place any of the 5 registered
   `PolicyDefinition` rows are constructed is
   `database/factories/PolicyDefinitionFactory.php` — test-only, never
   invoked outside `tests/`. R-02's description already named all 5
   actions as of Session 12; this session's contribution is confirming
   that scope is still accurate (0 of 5 covered) and adding an explicit
   re-verification note so a future session doesn't have to re-derive it.
2. **R-04 added**, tracking ADR-0003's external chain-anchoring — never
   implemented, only the hash-chain and DB-grant (R-01) layers exist.
   ADR-0003 itself anticipated "chain-only" as an accepted, weaker
   degraded state, so this is a known, designed-for gap, not a surprise —
   but per the brief, it needed to be tracked rather than forgotten.
   Grouped with R-02 and the Demo Instance Data Safety controls as
   deployment-phase (Session 8) work. **Not implemented this session**,
   per explicit instruction.

Neither R-01 nor any ADR was touched.

## Part B — embeddable consent widget + public DSAR portal

### Widget: a standalone Vite library build, not an Inertia page

`resources/js/widget/` (`ConsentWidget.vue`, `main.js`), built by a
**second, independent Vite config** (`vite.widget.config.js`, library
mode, IIFE, fixed filename) to `public/widget.js` — deliberately not
routed through `laravel-vite-plugin`'s manifest-hashed pipeline, which
only serves pages this app itself renders. `npm run build` now runs both
configs; `npm run build:widget` runs just the widget. Full rationale in
`09-decision-log.md`.

Proven genuinely embeddable, not just built that way: `public/
embed-example.html` is a plain static HTML file with zero Blade/Inertia
involvement — a stand-in for a real third-party page — and the E2E test
drives it with a real headless browser.

**Widget deliberately has no client-side persistence** (no
`localStorage`/cookies) — found mid-session that an earlier draft
violated `03-architecture.md`'s own component-responsibility table
("[the widget is] not responsible for... storing anything client-side
beyond what's needed to render; no local persistence of consent state"),
written at Session 3. Fixed before this became a real architectural
drift: withdrawal is offered only for the consent just captured in the
same page view (a plain Vue ref, gone on reload), not a persisted
return-visit lookup.

### DSAR portal: reuses the existing signed link, invents nothing

`resources/js/Pages/DsarSubmit.vue` (`/dsar`) and `DsarStatus.vue`
(`/dsar/status/{signedToken}`), both plain Inertia pages matching
`Welcome.vue`'s existing convention. Neither the `POST /api/v1/dsar` nor
`GET /api/v1/dsar/status/{signedToken}` contracts changed at all. The
status page's own route does not re-check the signature — it forwards
`signedToken` plus whatever query string it was loaded with straight to
the real, unchanged API path client-side, so the exact signature minted
by `DsarController::submit` is what actually gets validated. Full
reasoning in `09-decision-log.md`.

`DsarSubmit.vue` rewrites the API's raw `status_url` (`/api/v1/dsar/
status/{token}?...`) to the friendly page path (`/dsar/status/{token}?...`,
same query string) and navigates there on success — a UX improvement to
where a human lands, not a contract change.

### The actual proof: `tests/Browser/DsarLifecycleTest.php`

New dependency: **`pestphp/pest-plugin-browser`** (`^4.3`), not raw
Playwright or Dusk — chosen specifically because its `LaravelHttpServer`
driver dispatches every browser-originated request through the *same*
in-process application instance the rest of a Pest test runs in,
including carrying a plain `$this->actingAs($user)` session into the
browser automatically. That mattered concretely: **there is no staff
login UI** (see finding below), so the test's admin verify/approve steps
are ordinary `actingAs()->postJson()` calls sharing the same
`RefreshDatabase` transaction as the browser-driven steps — not a
separate, harder-to-build HTTP session bridge. Full choice rationale,
including why this over Dusk/raw Playwright, in `09-decision-log.md`.

The test drives the actual Success-Metric-1 cycle: gives consent via the
widget on a plain third-party-style static page → submits an erasure DSAR
via the public portal → an admin verifies identity and a *different*
admin approves erasure (ADR-0007 separation-of-duties) → a real
connector callback (HMAC-signed, matching `ConnectorDispatchTest.php`'s
existing pattern) completes it → the visitor's bookmarked status page
shows `complete` and the deletion certificate → the visitor withdraws
consent on the original widget page. Asserts real data-level outcomes
(`ConsentRecord.status`, `DsarRequest.status`, certificate existence),
not just that pages render.

**Two real bugs found and fixed by actually running this test
repeatedly, not by inspection** — full detail in `09-decision-log.md`:
1. `pestphp/pest-plugin-browser`'s own cleanup crashes without the
   `pcntl` PHP extension (references the bare `SIGTERM` constant
   unconditionally). Added `pcntl` to `docker/Dockerfile` and the new
   `e2e` CI job's PHP extensions list.
2. The widget silently failed to mount in a real browser
   ("`process is not defined`") — Vite's library mode skips its usual
   default of replacing `process.env.NODE_ENV`, which Vue's
   bundler-targeted build checks at runtime. A smoke test that only
   checked for static page text passed while the actual widget was dead
   — exactly why the brief asked for a real browser test. Fixed with an
   explicit `define` in `vite.widget.config.js` (also shrank
   `widget.js` from ~105 KB to ~69 KB via better tree-shaking).

### Finding: no staff login mechanism exists anywhere in this application

Discovered while designing the E2E test's admin steps, not assumed —
`05-api-contracts.md` documents session-based `staffAuth`, and every
admin controller correctly checks `$request->user()`, but no controller,
route, or view anywhere calls `Auth::login()` or renders a login form.
The only place a staff session is ever established is Pest's
`actingAs()` test helper. Concretely: **today, a real browser with real
credentials cannot become an authenticated staff session at all** — this
is a materially more fundamental gap than "richer admin dashboard" in
`11-backlog.md` (which implies a dashboard is merely undecorated). Not
fixed this session — building a login system is a new feature, out of
scope for "consent widget + DSAR portal UI." The README's walkthrough
documents the honest workaround (calling the gated controllers directly
via `tinker`, still going through the real `PolicyEvaluator` gate) rather
than pretending a login flow exists.

### README rewrite

`README.md` had not been updated since Session 5 (still read "Session 5
complete, no features implemented yet" despite 7 sessions of real
feature work) — this undermined Success Metric #1 categorically, since a
stranger reading it would have no idea any of this existed. Rewrote the
status line and feature summary, and added a full numbered "Try it"
walkthrough covering the real 15-minute cycle, including the one-time
policy/purpose bootstrap tinker snippet (R-02) and the admin-step tinker
workaround (no login UI, above).

## What was explicitly NOT done this session, and why

1. **R-01 — untouched**, per ground rules (not trivially resolved as a
   side effect).
2. **No ADR reopened.** The widget's client-side-persistence removal
   restored compliance with an existing `03-architecture.md` table, it
   didn't change the table itself.
3. **The audit-log external chain-anchor (R-04) was not implemented** —
   tracked only, per explicit instruction.
4. **No staff login UI built** — see the finding above; out of scope for
   this session's stated objective.
5. **The seeder itself (R-02) was not built** — the README's tinker
   bootstrap is a documented, honestly-labelled stand-in, not a fix.

## Files created or changed

**Frontend:** `resources/js/widget/ConsentWidget.vue` (new),
`resources/js/widget/main.js` (new), `resources/js/Pages/DsarSubmit.vue`
(new), `resources/js/Pages/DsarStatus.vue` (new), `resources/js/Pages/
Welcome.vue` (nav links added), `vite.widget.config.js` (new),
`public/embed-example.html` (new).

**Routes:** `routes/web.php` (`/dsar`, `/dsar/status/{signedToken}`).

**Build/tooling:** `package.json` (`build:widget` script, `build` now
runs both configs, new `playwright` devDependency required by the E2E
test tooling below), `package-lock.json`.

**Testing:** `tests/Browser/DsarLifecycleTest.php` (new), `tests/Pest.php`
(binds `Browser` directory), `composer.json` (new
`pestphp/pest-plugin-browser` dev dependency, `test:e2e` script,
`process-timeout: 900` — Pest Browser Testing's cold-start genuinely
exceeds Composer's 300s default on this machine).

**Infrastructure:** `docker/Dockerfile` (`pcntl`/`sockets` PHP
extensions, Node.js 20, `npm ci` + `npx playwright install --with-deps
chromium` — all dev-image-only, per the file's own existing "not a
production artifact" framing), `docker-compose.yml` (`node_modules`
anonymous volume on `app`, matching the existing `vendor` pattern),
`.github/workflows/ci.yml` (new `e2e` job).

**Docs:** `README.md` (rewritten — see above), `CONTRIBUTING.md`
(`composer test:e2e` documented), `docs/project-memory/
10-risk-register.md` (R-02 re-verified, R-04 added),
`docs/project-memory/09-decision-log.md` (5 new entries: widget
architecture, status-page signed-link reuse, Pest Browser Testing
choice, the two bugs found/fixed, the no-staff-login finding),
`docs/project-memory/07-testing-strategy.md` (new "End-to-end / browser
testing" section), `docs/project-memory/01-scope-and-non-goals.md` (MVP
boundary checklist re-checked item-by-item — see below), this file.

## Validation performed

- `docker compose exec app vendor/bin/pest` → **150/150 passed** (no
  regressions; this session added no new backend business logic).
- `docker compose exec app vendor/bin/pest tests/Browser` →
  **1/1 passed (14 assertions)**, run twice to confirm stability
  (18.55s and 12.33s) after both bugs above were fixed.
- `vendor/bin/pint --test` → clean, 140 files.
- `vendor/bin/phpstan analyse --memory-limit=1G` (Larastan level 8) →
  no errors.
- `npm run lint` → clean.
- `npm run build` → both configs succeed (`public/build/` and
  `public/widget.js`).
- `docs/architecture/openapi.yaml` validated with
  `openapi_spec_validator` (throwaway `python:3.12-slim` container) →
  **OK**. No changes were needed — no API contract changed this session.
- No new migrations — rollback-parity check not applicable.
- **Not yet pushed** — awaiting confirmation before push, matching the
  established pattern from prior sessions.

## MVP-completeness check (re-run against the actual codebase)

Checked `01-scope-and-non-goals.md`'s MVP boundary checklist item-by-item
again. **Verdict: 7 of 9 items are genuinely complete** (up from 5 at
Session 12); the remaining 2 are independent, narrower gaps rather than
a single shared root cause:

| # | Item | Status |
|---|---|---|
| 1 | Consent registry | ✅ Complete (widget added this session) |
| 2 | DSAR | ✅ Complete (portal added this session) |
| 3 | Retention policies | ✅ Complete |
| 4 | RoPA register with export | ✅ Complete |
| 5 | Tamper-evident audit log | Hash chain complete; **no periodic external anchor (R-04)** |
| 6 | ABAC authorisation | ✅ Complete |
| 7 | Single organisation per instance | ✅ Complete |
| 8 | GDPR/UK-GDPR only | ✅ Complete |
| 9 | Public demo instance | **Not done** — no seeders (R-02) |

**The Success Metric #1 walkthrough is now functionally completable**,
but with an honest asterisk: it requires the one-time tinker bootstrap
(R-02, no seeder) and a tinker-based admin-action workaround (no staff
login UI, a new finding this session — see above). Whether a genuinely
*naive* stranger (as opposed to the technical-founder persona the
project brief targets) could complete it unassisted in the 15-minute
window is a judgment call, not something this session can assert as a
pass — flagged explicitly rather than claimed. A stopwatch-timed run by
an actual third party, not just functional completeness, is the honest
next check if this metric needs to be certified rather than just
plausible.

The project is closer to MVP-complete than at any prior session but
still cannot be credibly tagged v1.0.0 per its own Definition — R-02 and
R-04 remain, and Success Metric #1's "under 15 minutes" claim has not
been independently timed.

## Open questions and risks

- **R-01 — unchanged.**
- **R-02 — re-verified, still open.** Gap is 0 of 5 policies seeded, no
  `database/seeders/` content, confirmed this session.
- **R-04 — new, open.** ADR-0003's external chain-anchor never built.
- **No staff login mechanism** — new finding this session (see above);
  not a formal risk-register entry (out of this session's Part A scope),
  but a real, load-bearing gap worth a future session considering
  whether it deserves one.
- **Public demo instance / seeders** — unchanged, still not built.
- **Success Metric #1's "under 15 minutes" claim** — functionally
  completable now, but not independently stopwatch-verified, and the
  admin-step tinker workaround means it's not yet a *pure* browser
  experience for a non-technical stranger.

## Next recommended session

- Proposed session title: **either** the audit-log periodic anchor
  (R-04, ADR-0003's remaining half) **or** a minimal staff login UI (the
  new finding this session, which would also make the DSAR admin steps
  demonstrable through a browser rather than tinker) **or** the
  demo-instance seeder (R-02) as part of Session 8 deployment prep — all
  three are now genuine, independent "Must"-adjacent gaps with nothing
  else blocking them.
- Inputs required: `docs/project-memory/12-session-handoff.md` (this
  file), `docs/project-memory/10-risk-register.md` (R-02/R-04),
  `docs/adr/ADR-0003-audit-log-tamper-evidence.md` (if the anchor is
  chosen).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 13
complete, **not yet pushed** (awaiting confirmation).

**Current stack:** Laravel 12, Vue 3/Inertia, PostgreSQL, Redis,
S3-compatible storage, `barryvdh/laravel-dompdf`, **plus
`pestphp/pest-plugin-browser` (new this session, dev/test-only — real
browser E2E testing, not a runtime dependency) and Node.js/Chromium in
the dev Docker image to support it.**

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0–12 remain in force. Five new decision-log entries this
session (widget architecture, status-page link reuse, Pest Browser
Testing choice, two bug fixes, the no-staff-login finding) — none is an
ADR, none reverses or extends an existing ADR's trade-off.

**Implementation state:**
- Done: everything from Session 12, plus: embeddable consent widget
  (`public/widget.js`), public DSAR portal (`/dsar`, `/dsar/status/
  {signedToken}`), a real browser-driven E2E test proving the full
  consent → withdrawal → DSAR erasure → completion cycle.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) still no bootstrap/seeder for
  `PolicyDefinition` rows (R-02); (2) no staff login UI anywhere (new
  finding); (3) the audit-log external chain-anchor is unbuilt (R-04);
  (4) no connector is registered by default; (5) Success Metric #1's
  15-minute claim is functionally plausible but not stopwatch-verified.
- Not started: the audit-log periodic anchor, a staff login UI, the
  public demo instance/seeders, connector secret rotation, HTTP
  connector-management (deliberately deferred), email/notification
  delivery for export/certificate readiness (deferred), the
  `RetentionPolicyController::store` duplicate-active-policy validation
  gap (Session 12 finding, still open).

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — `pestphp/pest-plugin-browser` is
dev/test tooling, not a new architectural pattern, and does not count
against it.

**Task for next session (single objective):** the audit-log anchor
(R-04), a minimal staff login UI, or the demo-instance seeder (R-02) —
see "Next recommended session" above; the user should confirm which
before the next session starts.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/10-risk-register.md` (R-02/R-04)
- `docs/adr/ADR-0003-audit-log-tamper-evidence.md` (if the anchor is chosen)

**Ground rules:** Do not change the stack beyond tooling additions
already made. Do not reopen any existing ADR. R-01/R-02/R-04 remain open
— do not fold a fix in silently.
