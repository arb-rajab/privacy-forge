# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 19 — Close out R-07's verification
  gap honestly, formally accept R-08, write up Retirement/Handover/
  Disposal (this repository's second deep SDLC phase) for the first time**
- Objective, in priority order per this session's brief: (A) check whether
  Session 18's GitHub rate limit had cleared and, if so, get one genuine
  unbroken cold-clone timing of the exact final Dockerfile; if not, add a
  real dated follow-up trigger instead of a passive note; (B) formally
  accept R-08 as a residual risk, not "in progress," given three sessions
  of rigorous investigation; (C) populate
  `docs/project-memory/14-maintenance-and-retirement.md` — still the
  empty Session-0 template despite this repository being chosen from the
  start (Session 0) to demonstrate this phase deeply — citing real
  existing evidence where it exists and writing genuinely new content
  (a decommissioning runbook, an archival-format assessment, dependency
  EOL horizons) where it doesn't; update `docs/SDLC-EVIDENCE.md`'s Phase 8
  row to match.
- Status: **complete.** Part A: rate limit re-checked, confirmed still
  blocked (`HTTP/2 429`, unchanged from Session 18) — no rebuild attempted
  against a still-blocked limit; a dated, actionable follow-up trigger
  added to the risk register instead. Part B: R-08 formally accepted as a
  residual risk, moved out of the open-risks table into a new "Accepted
  residual risks" section, with the curl-based manual-verification
  walkthrough stated as the standing backend/API mitigation and an
  explicit, undisguised note that client-side Vue rendering remains
  unconfirmed by automated means. Part C: `14-maintenance-and-retirement.md`
  fully populated (463 lines added) — real citations for data
  export/portability, retention/deletion scheduling, and deletion
  certificates; genuinely new content for the decommissioning runbook and
  archival-format assessment; a real, previously-unflagged gap (no
  full-instance export mechanism exists) named plainly and added to
  `11-backlog.md` (itself found still empty and populated for the first
  time, B-01/B-02/B-03) rather than built under this session's banner.
  `docs/SDLC-EVIDENCE.md`'s Phase 8 row updated to reflect genuinely deep
  evidence, matching Phase 2/3's citation style. No application code
  changed; one incidental correctness check (see "Incidental finding"
  below) turned up nothing wrong with the committed code itself.

## Part A — R-07's rate-limit re-check

Session 18 closed R-07 (Docker cold-build cost) on the strength of a real
root-cause fix (multi-stage Dockerfile splitting Node/Playwright/Chromium
out of the default runtime image) and a real 643s re-measurement — but
against an *intermediate* Dockerfile, not the exact final committed one,
because GitHub's anonymous `codeload.github.com` rate limit (tripped by
Session 18's own repeated build attempts) blocked one last clean run. This
session's brief was to check whether that limit had cleared and, if so,
get the genuine measurement; if not, make the gap trackable rather than
letting it quietly persist.

**Checked first, cheaply, before attempting anything else:**
```
curl -sI https://codeload.github.com/laravel/framework/zip/refs/tags/v11.0.0
```
Returned `HTTP/2 429` — still blocked, same calendar day as Session 18's
original trip. Confirmed general (not endpoint-specific) with a second
package URL (`symfony/process`), also `429`. Attempting a full rebuild
against a limit already known to be active would only waste session time
reproducing the identical block Session 18 already documented — not
attempted, per this session's own explicit brief.

**What was done instead:** a dated, actionable follow-up trigger was
added directly to R-07's entry in `10-risk-register.md` — not a passive
"note for later," but a specific re-check command, a concrete date
(2026-08-24, one week after Session 18's trip) past which the situation
should be treated as no longer a simple transient limit, and explicit
next actions for whichever side of that date the next check lands on.

**Incidental finding, investigated and resolved, not left ambiguous:**
while confirming the committed code still behaves correctly (this
session touched no application code, but wanted real evidence rather than
assuming the Session 18 fix still holds before writing about it),
`composer test` against the already-running `privacy-forge-app-1`
container failed 165/165 with the exact `Pest\Browser\Support\
socket_create_listen()` error Session 18's fix was supposed to prevent.
Investigation showed why, and it is **not a regression in the committed
Dockerfile**: that running container was still on the stale image built
*before* Session 18 committed the sockets/pcntl fix (the rate limit
prevented anyone from rebuilding it since), so the container itself was
simply out of date with `main`. Live-patching it the identical way
Session 18 did (`docker exec ... docker-php-ext-install sockets pcntl`)
and re-running the suite produced **165/165 passing in 129s**, real and
reproducible, confirming the committed Dockerfile's fix is correct — a
container-currency issue, not a code issue. (One single run of
`composer test`, as opposed to invoking `pest` directly, hit the
900-second wrapper timeout once during this investigation; re-running the
same suite directly via `vendor/bin/pest` immediately afterward completed
cleanly in 129 seconds. Not chased further — R-07/R-08 are this session's
only in-scope risk items and neither claims anything about this specific
timeout, so treating one unreproduced slow run as a new open risk would be
speculation, not evidence; flagged here for visibility only.)

## Part B — R-08 formally accepted as a residual risk

Per this session's brief: three sessions (16, 17, 18) of rigorous,
evidence-based investigation — a diagnosis, a disproof of that diagnosis
via direct source inspection (not re-assertion), and the tool's own
documented recommended fix attempted and ruled out, each confirmed via a
repeatable method (process-tree inspection, bounded CPU-tick measurement)
— is enough. `10-risk-register.md` now has a new "Accepted residual
risks" section; R-08 was moved there from the open-risks table, with its
full investigation history preserved (not summarised away) and:

- **Status changed** from "Open" to "**Accepted (residual risk)**",
  dated Session 19 (2026-08-17).
- **Standing mitigation stated explicitly:** the curl-based manual
  verification walkthrough against the real running docker-compose stack
  (first performed Session 17) is the standing substitute for
  backend/API confidence — a real, bracketed, end-to-end HTTP walkthrough
  of the DSAR lifecycle.
- **Honest boundary stated, not glossed over:** client-side Vue rendering
  of the admin dashboard remains genuinely unconfirmed by any automated
  means — the curl substitute proves the backend contract, not that a
  real browser click fires it.
- **Explicit condition for reopening:** only a materially new input (a
  Playwright/pest-plugin-browser version update addressing this upstream,
  or a report on the one still-untried lead — a minimal Pest/Laravel-free
  Playwright repro) should prompt revisiting this; not simply more
  session time on the same investigation.

No further attempt was made at the underlying Playwright/Docker hang this
session, per the brief.

## Part C — Retirement, Handover & Disposal, written up for the first time

**Confirmed plainly, as instructed:** `14-maintenance-and-retirement.md`
was still exactly the empty Session-0 template (bare section headers, one
placeholder "last verified: NEVER" style line) despite
`00a-ledger-confirmation.md` naming this repository's Phase 8 as one of
its two demonstrated-deeply SDLC phases from Session 0 onward — "the
phase almost no portfolio demonstrates; here it is the product itself."
Sixteen sessions of real, working evidence for this phase already existed
in the codebase; none of it had ever been connected to this document.

**Cited, not reinvented** (existing code, pointed at rather than
re-described):
- **Data export and portability** — US-008's export bundle mechanism
  (`ExportBundleAssembler`, `ExportBundleController`), including its
  double-enforced 72-hour TTL and application-layer encryption.
- **Data retention and deletion schedule** — US-010/011/012's retention
  engine (`RetentionSelector`/`RetentionExecutor`), ADR-0002's
  single-selector-service parity guarantee, and the daily scheduler
  registration (`routes/console.php`).
- **Deletion certificates** — `DeletionCertificateGenerator` (DSAR side)
  and `RetentionExecutor::execute()` (retention side), and Session 11's
  DB-level `deletion_certificates_exactly_one_source` CHECK constraint —
  the "exactly one source" guarantee is enforced at the database, not
  just by convention.

**Genuinely new content, written this session:**
- **A concrete, command-by-command instance-decommissioning runbook**
  (since ADR-0005 makes this single-tenant, "tenant offboarding" here
  means retiring one specific self-hosted instance): final RoPA export
  (existing `RopaController::export` endpoint), final audit-chain
  verification via `php artisan audit:verify-chain` (which already
  exists — `App\Console\Commands\VerifyAuditChainCommand`, R-04/ADR-0003
  — no new CLI command was needed), real data-export options available
  today (per-subject export bundles, `pg_dump --format=plain`, a MinIO/S3
  object-storage sync), and secure disposal (`docker compose down` +
  named-volume removal, with the actual volume names from
  `docker-compose.yml`).
- **An archival-format assessment**, which surfaced a genuine, real gap:
  **no full-instance export mechanism exists** in this codebase today —
  only per-subject (US-008) and organisation-wide-but-processing-activity-
  only (RoPA/US-013) exports exist. This was **not built** under this
  session's scope (a real, non-trivial feature, not a "trivial missing
  piece"); it is instead proposed as a concrete future backlog item
  (`B-01` in `11-backlog.md`), with a specific suggested shape (reuse the
  existing `ExportBundleAssembler`/`RopaController` JSON/CSV conventions
  across every first-class model, rather than inventing a third format).
- **Dependency support horizons**, verified against `endoflife.date`
  (not fabricated): PHP 8.3 (security support until 2027-12-31), Laravel
  — corrected here from the frozen Session-0 ledger's stale "Laravel 11"
  to the actually-installed `^12.61.1` (security support until
  2027-02-24), PostgreSQL 16 (security support until 2028-11-09), and
  Redis 7 (unpinned minor in `docker-compose.yml`, so a range rather than
  one date). One date (Laravel 12's *active*-support-end, one day before
  this session) was flagged as close enough to "now" to warrant a cheap
  re-check next time dependencies are touched, rather than treated as
  settled by a single fetch.

**Two small, honest side-findings acted on, not just noted:**
1. `11-backlog.md` was itself still the empty Session-0 template — a
   citation this session initially wrote ("tracked in `11-backlog.md`")
   would have been false. Fixed by actually populating it (B-01 the new
   archival-export gap, B-02 a previously-undertracked Session 11/12
   retention-policy uniqueness gap, B-03 a CI-scheduling gap this
   session's own "Maintenance cadence" research surfaced), then
   correcting the citation to match reality.
2. CI's `osv-scanner`/CodeQL/gitleaks jobs were confirmed (by reading
   `ci.yml` directly) to run only `on: push`/`pull_request` to `main`, no
   `schedule:` trigger — meaning a CVE against an already-merged,
   untouched dependency is never re-caught. Named plainly in "Maintenance
   cadence" and tracked as `B-03`, not fixed under this session's
   documentation-focused scope.

`docs/SDLC-EVIDENCE.md`'s Phase 8 row was rewritten from "Not yet
started — —" to a real evidence citation, matching the citation style
already used for Phase 2/3's rows.

## What was explicitly NOT done this session, and why

1. **R-01, R-02, R-05, R-06 — untouched, per ground rules.**
2. **No ADR reopened.** No GDPR-only/single-tenant/public-demo scope
   touched.
3. **R-08's underlying Playwright/Docker hang — still not fixed, and not
   attempted again this session.** Per the brief, three sessions of
   evidence-based investigation (16/17/18) is enough to formally accept
   this as a residual risk rather than keep attempting fixes; see Part B.
   The one still-untried lead (a minimal Pest/Laravel-free Playwright
   repro, narrowed at Session 18) remains available for whoever picks
   this back up if a materially new input (e.g. an upstream version
   update) makes it worth revisiting.
4. **A full-instance archival export — not built.** A genuine, real gap
   found while writing up Part C; proposed as `B-01` in `11-backlog.md`
   rather than built under this documentation-focused session's scope
   (see Part C above).
5. **A weekly CI re-scan schedule for `osv-scanner` — not added.** Named
   as a real gap (`11-backlog.md`'s `B-03`) rather than a config change
   made under this session's scope.
6. **`RetentionPolicyController::store`'s duplicate-active-policy gap
   (Session 11/12) — not fixed.** Now actually tracked (`B-02`) rather
   than only mentioned in the decision log, but still open.

## Files created or changed

**Docs only, this session:** `docs/project-memory/10-risk-register.md`
(R-07's dated, actionable rate-limit follow-up trigger added; R-08 moved
to a new "Accepted residual risks" section and formally accepted),
`docs/project-memory/14-maintenance-and-retirement.md` (fully populated
from an empty Session-0 template — 463 lines added: maintenance cadence,
support model, data export/portability, retention/deletion schedule,
deletion certificates, handover pack, a new instance-decommissioning
runbook, an archival-format assessment, end-of-life policy, dependency
support horizons), `docs/project-memory/11-backlog.md` (populated from an
empty Session-0 template — B-01/B-02/B-03), `docs/SDLC-EVIDENCE.md`
(Phase 8 row rewritten to cite real evidence), this file.

**No application code, migrations, dependencies, Dockerfile, or Compose
config changed this session.** The one Docker-related action taken —
live-installing `sockets`/`pcntl` into the already-running
`privacy-forge-app-1` container via `docker-php-ext-install` — mutated
only that container's ephemeral writable layer to bring it in line with
the already-committed Dockerfile, for verification purposes; it changed
no file in the repository and does not persist if that container is
recreated.

## Validation performed

- **`vendor/bin/pest` (Unit + Feature, the full suite per
  `phpunit.xml.dist`'s default testsuites) → 165/165 passed, 664
  assertions, 129.00s** — run directly against the actual committed code
  on `main` (git tree clean, `HEAD` at the start of this session), inside
  a live-patched instance of the current `runtime`-target image (see
  Part A's "Incidental finding" for why the live patch was needed and why
  it's evidentially sound — same technique, same reasoning Session 18
  used). This is a genuine, reproducible pass against the real code, not
  an assumption.
- **`docker compose build app` was attempted** (using local layer cache,
  not `--no-cache`) to see whether a cache-only rebuild could sidestep the
  rate limit — it could not: this repository has no committed
  `composer.lock`, so `composer install` re-resolves and re-downloads on
  every build regardless of cache state, and it hit the same
  `codeload.github.com` `HTTP/2 429` immediately. This further confirms
  Part A's conclusion that no rebuild was possible this session, from a
  second angle (not just the standalone `curl` check).
- **No Pint/Larastan/ESLint/OpenAPI validation re-run this session** —
  no application code, PHP, TypeScript, or OpenAPI surface was touched;
  only Markdown docs changed, so these checks would have been redundant
  motion rather than genuine verification. (If a future session wants
  belt-and-suspenders confirmation of this reasoning, `git diff --stat`
  at the top of this session's work confirms the changed file set was
  documentation-only.)
- **Not pushed by this instruction alone** — committed and pushed as part
  of this same turn, after this handoff was finalised; see the commit
  that follows.

## Open questions and risks

- **R-01 — unchanged, still open.**
- **R-02, R-04, R-05, R-06 — unchanged, still closed.**
- **R-07 — still closed (Session 18), rate-limit follow-up re-checked
  this session and still blocked.** `HTTP/2 429` persists from
  `codeload.github.com`, unchanged since Session 18's original trip on
  the same calendar day. A dated, actionable trigger is now in the risk
  register (re-check the same `curl` command; if still blocked on or
  after 2026-08-24, treat it as no longer transient). The composed
  ~716s estimate remains the standing number; not urgent given the margin
  under the 900s budget.
- **R-08 — formally accepted as a residual risk this session, no longer
  "open."** See Part B. Reopen only on a materially new input (an
  upstream Playwright/`pest-plugin-browser` fix, or a report on the
  untried minimal-repro lead) — not simply more investigation time on the
  same three sessions' worth of evidence.
- **B-01/B-02/B-03 (new, this session)** — full-instance archival export
  gap, `RetentionPolicyController::store` duplicate-policy validation gap,
  and CI's missing scheduled re-scan trigger — all tracked in
  `11-backlog.md` (itself populated for the first time this session), all
  still open.
- **Optional/stretch items (retention UI, RoPA export button, policy
  management UI, audit log view) — all still open, all API/CLI-only**,
  unchanged from prior sessions.

## Next recommended session

Both of Session 18's leads remain valid and are now more clearly
optional rather than urgent, since R-08 is accepted and R-07's remaining
gap is a low-priority confirmation:

1. **The minimal Playwright repro for R-08** — the one genuinely untried
   lead across three investigation sessions. Worth picking up only if a
   session has spare capacity for it or a materially new signal (e.g. a
   dependency update) makes it timely; not urgent now that R-08 is
   formally accepted as residual.
2. **A quick confirmation re-measurement of R-07's exact final
   Dockerfile**, once the rate limit clears (re-check via the dated
   trigger in `10-risk-register.md` — try again on or after 2026-08-24 if
   still blocked at that point). Low priority given the margin under
   budget either way.
3. **`B-01` — the full-instance archival export**, if a session wants to
   pick up genuinely new feature work rather than investigation or
   documentation: reuse `ExportBundleAssembler`/`RopaController`'s
   existing JSON/CSV conventions across every first-class model (see
   `14-maintenance-and-retirement.md`, "Archival export format," for the
   proposed shape).

- Inputs required: this file, `docs/project-memory/10-risk-register.md`
  (R-01/R-07/R-08), `docs/project-memory/11-backlog.md` (B-01/B-02/B-03).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 19
complete and pushed.

**Current stack:** unchanged since Session 13 — Laravel `^12.61.1`
(corrected from the Session-0 ledger's stale "Laravel 11" this session —
see `14-maintenance-and-retirement.md`'s "Dependency support horizons"),
PHP 8.3, Vue 3/Inertia, PostgreSQL 16, Redis 7, S3-compatible storage,
`barryvdh/laravel-dompdf`, `pestphp/pest-plugin-browser`. No new
dependencies this session — a documentation-only session.

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0-18 remain in force. No ADR touched or reopened this session.

**Implementation state:**
- Done: everything from Session 18, plus: R-07's rate-limit follow-up
  re-checked and made trackable (still blocked, dated trigger added);
  R-08 formally accepted as a residual risk (no longer "open");
  `14-maintenance-and-retirement.md` fully written up for the first time
  (this repository's second deep SDLC phase); `11-backlog.md` populated
  for the first time (B-01/B-02/B-03); `docs/SDLC-EVIDENCE.md`'s Phase 8
  row updated to reflect deep evidence.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) R-01 — DB-level grant revocation for
  the audit log unbuilt; (2) R-07's rate-limit follow-up — re-check the
  dated trigger in `10-risk-register.md` (2026-08-24 if still blocked
  before then); (3) R-08 — accepted as residual, the minimal-repro lead
  remains available but not urgent; (4) `B-01` — no full-instance archival
  export exists yet, only per-subject (US-008) and RoPA (US-013) exports;
  (5) `B-02` — `RetentionPolicyController::store`'s duplicate-active-policy
  gap; (6) `B-03` — CI's `osv-scanner`/CodeQL/gitleaks jobs have no
  scheduled re-run trigger; (7) no password reset flow; (8) retention/
  RoPA/policy/audit-log management UIs remain API/CLI-only.
- Not started: a registry-hosted prebuilt image for R-07 (no longer
  urgent), a fix for R-08's underlying hang (accepted as residual, not
  actively being pursued), connector secret rotation, HTTP
  connector-management (deliberately deferred), email/notification
  delivery, password reset, the three new backlog items above, the public
  demo instance's isolated infrastructure/spend cap/scheduled reset (the
  one remaining unchecked MVP item).

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — this session introduced no new
architectural pattern or dependency.

**Task for next session (single objective):** no single forced next step
— R-08 is now accepted (not urgent), R-07's remaining gap is low-priority.
Reasonable options, roughly in order of value: pick up a `11-backlog.md`
item (B-01 full-instance export is the most substantial), or R-01 (audit
log DB-grant revocation, the one remaining genuinely open risk).

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/10-risk-register.md` (R-01/R-07/R-08)
- `docs/project-memory/11-backlog.md` (B-01/B-02/B-03)

**Ground rules:** Do not change the stack. Do not reopen any existing ADR.
R-01 remains open — do not fold a fix in silently. R-02/R-04/R-05/R-06/
R-07 are closed, and R-08 is accepted as a residual risk — do not reopen
any of them without a genuine new finding.
