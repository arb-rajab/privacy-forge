# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 18 — Push Session 17, fix R-07's
  Docker build cost at its actual root cause, attempt R-08's root cause
  once, revise Success Metric #1's wording honestly**
- Objective, in priority order per this session's brief: push Session 17's
  unpushed commit; (A) investigate whether Playwright/Chromium is baked
  into the same image `docker compose up` builds and runs, fix it if so,
  re-measure; (B) one targeted attempt at R-08's root cause; (C) revise
  Success Metric #1's wording to separate reviewer/self-hoster/product-
  walkthrough timing, recorded as a decision-log revision.
- Status: **complete.** Session 17 pushed immediately. Part A confirmed
  the hypothesis, fixed it, and closed R-07 with a real re-measurement.
  Part B made a genuine, evidence-based attempt and the hang did **not**
  resolve — R-08 stays open, reported plainly. Part C's revision is in
  place with full reasoning in the decision log. One real regression was
  found by actually running the tests (not assumed) and fixed. One
  external constraint (a GitHub anonymous rate limit this session's own
  repeated builds tripped) prevented one final unbroken cold-clone timing
  run of the exact committed Dockerfile — reported honestly, not hidden,
  with a directly-measured functional substitute in its place.

## Part A — R-07: is Playwright/Chromium baked into the runtime image, and can it be fixed?

**Confirmed, and fixed.** `docker/Dockerfile` was a single, undifferentiated
build stage. The *same* image `docker-compose.yml`'s `app`/`worker`
services build and run by default (`docker compose up --build`, no test
flags) unconditionally ran `npm ci && npx playwright install --with-deps
chromium` — installing Node.js, npm, Playwright, and a real downloaded
Chromium into an image whose only job, at runtime, is `php artisan serve`.
None of that tooling is ever touched by application code — only by
`pestphp/pest-plugin-browser`'s `tests/Browser/` suite. A stranger running
the plain app was paying for browser-testing tooling they would never use.

### The fix

Split `docker/Dockerfile` into a shared `deps` base and two final targets:

- **`runtime`** — no Node.js, no npm, no Playwright, no Chromium, none of
  the OS packages `playwright install --with-deps` pulls in. This is what
  `docker-compose.yml`'s `app` service now builds via `target: runtime`,
  and what `worker` inherits (it reuses `app`'s tag, unchanged from
  Session 16's fix).
- **`test`** — extends the same shared `deps` layer with Node.js, npm, and
  Playwright/Chromium. Used only by a new Compose service, `app-e2e`,
  gated behind a Compose **profile** (`profiles: ["e2e"]`) so it is never
  built or started by a plain `docker compose up`/`up --build`. Run it
  explicitly: `docker compose --profile e2e run --rm app-e2e composer
  test:e2e` (documented in `CONTRIBUTING.md`).

`.github/workflows/ci.yml`'s `e2e` job was checked and found **not** to
build or use `docker/Dockerfile` at all — it runs directly on the GitHub
runner via `setup-php`/`setup-node`/`npx playwright install`. So this split
has no effect on CI either way; a short comment was added to the job
noting this explicitly, so a future session doesn't assume it needs
updating to match.

### Re-measurement

Repeated Session 17's exact procedure, on the same class of host (Windows
11 + Docker Desktop/WSL2): `docker compose down`, `docker rmi -f` on all
project images, `docker builder prune -af` (confirmed 0B cache/0 project
images via `docker system df` before starting — base images like
`postgres`/`redis`/`minio` were left cached, matching Session 17's own
methodology, which also didn't purge those), then a single bracketed
`docker compose up --build -d`.

**Result: 643 seconds (~10.7 minutes)**, timestamped via the app
container's first successful healthcheck log entry (not a rough estimate)
— down from Session 17's 2083s (~34.7 min), a ~69% reduction, now under
Success Metric #1's 900-second budget on its own. The final `app` image
also shrank from ~3.3GB to 1.32GB, consistent with removing Node/npm/
Playwright/Chromium.

### A real regression, found by actually running the tests

The first cut of the split moved `sockets`/`pcntl` (PHP extensions) into
the `test` target only, reasoning (from the decision log, Session 13) that
they're only ever *used* by `tests/Browser/`. Running `composer test`
(the plain Feature suite, not the browser suite) against that image failed
all 165 tests with `Call to undefined function
Pest\Browser\Support\socket_create_listen()`. Reading `vendor/pestphp/
pest-plugin-browser/src/Support/Port.php` explained why: the plugin hooks
into Pest's global test lifecycle at boot, regardless of which test file
is actually running, so the extension has to be present for **any** `pest`
invocation once the package is installed at all — and it's a `require-dev`
dependency either way, present in `composer.lock` regardless of which
Dockerfile target is building. This would have been a silent, serious
regression (`composer test` — the suite this project relies on for every
other risk's evidence — broken on the "fixed" image) if not caught by
actually running it rather than assuming the split was safe.

**Fix:** `sockets`/`pcntl` moved back into the shared `deps` stage (both
`runtime` and `test` build from it), not `test`-only. This did surface one
more thing worth recording honestly: this session's own first Dockerfile
comment claimed compiling those two extensions was "fast (a few seconds)"
— untested at the time it was written. Timing it directly afterward showed
**73 seconds**, not a few — still an order of magnitude below the
Node/Playwright/Chromium cost this split exists to remove, but materially
more than the comment first assumed. The comment was corrected in place
rather than left wrong.

### An external constraint this session hit, reported honestly

Getting the fix right took several full `docker build` cycles in a short
window (the initial flawed split, the regression discovery, the fix, and
verification attempts). This tripped GitHub's anonymous rate limit on
`codeload.github.com` (Composer's fallback zip-download source for many
packages) — every subsequent full build failed partway through
`composer install` with repeated `HTTP/2 429`s, on a rotating but
overlapping set of packages, for **well over an hour of real elapsed time
across five separate attempts** (including one with a GitHub personal
access token passed via `--build-arg COMPOSER_AUTH`, which did **not**
help — `codeload.github.com`'s legacy-zip endpoint is not gated by OAuth
token rate limits the way `api.github.com` is; that attempt was reverted
from the Dockerfile since it added a Docker secrets-hygiene lint warning
for zero actual benefit). This is a **session-specific artifact of this
session's own repeated testing**, not a repository defect — a real
stranger's single cold clone would not trip it.

Rather than keep retrying blindly, this session verified the fix's
*functional correctness* a different way that doesn't depend on
re-downloading Composer packages at all: started a container from the
already-built (pre-fix) `privacy-forge-app:latest` image — which already
has a complete `vendor/` from before the rate limit started — live-
installed `sockets`/`pcntl` via `docker exec ... docker-php-ext-install
sockets pcntl` (a local compile, no network dependency), and re-ran
`composer test` against it. **165/165 passed.** The isolated compile step
was also timed directly this way (the 73s figure above). Pint (152 files)
and Larastan (level 8) were run against the still-running pre-fix
container directly — both clean, since neither depends on the extension
fix.

**What this means for the R-07 number, stated precisely:** the 643s figure
above is real and directly measured, but it was measured on the
*intermediate, since-superseded* Dockerfile (before `sockets`/`pcntl` were
moved back to the shared stage). A fresh, single, unbroken cold-clone
timing of the *exact final, committed* Dockerfile could not be completed
this session — blocked by the rate limit above. The best-supported
estimate is **643s + 73s ≈ 716s (~11.9 min)**, composed from two real,
separately-measured numbers rather than guessed — still comfortably under
the 900s budget, but this specific sum is not itself a single measured
run. R-07 is closed on the strength of: the root cause identified and
fixed, correct behaviour re-verified functionally (165/165 tests), and a
well-supported (if composed, not atomic) time estimate under budget. A
future session revisiting this only needs one clean `docker builder
prune -af` + `docker compose up --build -d` cycle, once enough time has
passed since this session's own rate-limit trigger, to turn the estimate
into a single direct measurement — not urgent, given the margin.

## Part B — R-08: one targeted attempt at the root cause

**Attempted honestly, did not fix it.** Two candidate fixes were
identified in Session 17's own risk-register entry: switch Playwright's
CDP transport from pipe to WebSocket, or remove an unnecessary shell
wrapper if that's the actual fd-inheritance layer involved. Both were
investigated properly before attempting anything, and neither survived
scrutiny as originally framed:

1. **Re-read the actual source** (`vendor/pestphp/pest-plugin-browser/src/
   Playwright/Servers/PlaywrightNpmServer.php`, `ServerManager.php`)
   instead of re-asserting Session 17's theory. The `sh -c` shell Session
   17 flagged wraps the **PHP→Node** launch of `playwright run-server`
   (via Symfony Process's `fromShellCommandline`) — it does not sit
   between Node and the Chromium process Node subsequently spawns. The
   actual CDP pipe (fds 3/4) is set up by Node's own direct, shell-free
   `child_process.spawn()` call when Playwright's *already-running* Node
   process launches Chromium — a hop the outer `sh -c` has no way to
   affect. **This corrects, not just confirms, Session 17's mechanism
   theory**: removing that specific shell layer would not touch the fd
   path at all, so it was not attempted as a fix (there was nothing there
   to remove that could plausibly matter).
2. **Searched Playwright-core's own bundled source**
   (`node_modules/playwright-core/lib/coreBundle.js` and related files)
   for a documented pipe-vs-WebSocket toggle for a *locally launched*
   browser. There isn't one exposed via any public Playwright or
   `pest-plugin-browser` API — WebSocket-based CDP is only available when
   *connecting to an already-running remote browser*
   (`connectOverCDP()`), a fundamentally different usage pattern
   `pest-plugin-browser` doesn't use for its own launches. Switching
   transport would mean patching Playwright's own bundled, minified
   internals — ruled out as materially larger than this session's scope,
   matching Session 17's own reasoning for not patching vendor code.

With both of the brief's suggested angles closed off by direct
investigation, this session made **one concrete attempt at a different,
equally well-documented cause of exactly this symptom class**: Playwright's
own official Docker guidance names Docker's default 64MB `/dev/shm` as a
common cause of headless Chromium spawning a full process tree and then
making no further progress — which matches Session 17's exact symptom
(full process tree, near-zero CPU growth, no CDP traffic). Added
`shm_size: "1gb"` to the new `app-e2e` Compose service and re-ran
`composer test:e2e`.

**Result: unresolved.** Verified with the same rigour Session 17 used:

- Process tree inspected via `/proc/<pid>/cmdline` — a full Chromium tree
  spawned (browser, two zygotes, GPU process, network-service utility,
  two renderers), matching Session 17's own fuller-tree observation.
- Chromium's own launch arguments were inspected directly and already
  included `--no-sandbox` and `--disable-dev-shm-usage` automatically
  (Playwright applies both itself) — ruling out "missing sandbox/shm
  flags" as a separate, still-unexplored variable. The `shm_size` increase
  was a real, additional mitigation on top of flags already present, not
  a fix for a missing one.
- A clean, bounded 78-second window was measured (CPU-tick snapshots via
  `/proc/<pid>/stat` fields 14/15, taken before and after a fixed wait —
  not estimated): the browser process's accumulated CPU time grew by only
  ~20 ticks (~0.2s) — the same near-zero-progress signature Session 17
  measured (~8 ticks across several minutes), now re-confirmed on a fresh
  run with the fix applied.
- Zero new request or log activity appeared in that window.

The hung process tree was killed cleanly afterward
(`docker kill`/`docker rm -f`, then `docker compose --profile e2e down`).

**R-08 stays open, per the ground rules — not folded in as a silent
fix.** The two most commonly cited "quick fixes" for this failure class
are now both ruled out with direct evidence (missing sandbox/shm flags:
already applied by Playwright itself; CDP transport switch: not exposed
by any public API for a local launch). Sessions 14/15's specific
button-click claims were **not** re-checked this session, since the
suite still doesn't run — Session 17's manual-walkthrough substitute
remains the current fallback, unchanged.

## Part C — Success Metric #1's wording, revised

**Finding, stated plainly as a revision, not a silent edit** (same
discipline as the Owner-row correction at Session 10): the original
wording — "a stranger can self-host `privacy-forge` and complete a full
cycle... in under 15 minutes" — collapsed three different things into one
number: (1) a reviewer's experience, for which Session 1 already decided a
public hosted demo exists specifically so most reviewers never clone or
build anything locally; (2) a genuine self-hoster's one-time Docker
environment build, which Session 17 measured directly and (before this
session) exceeded the budget; (3) the actual product walkthrough once that
environment exists, which no session had separately measured.

Full reasoning is recorded in `09-decision-log.md`'s new entry. In brief:

- **Reviewer path:** no local build required; this metric's timing does
  not apply.
- **Self-hoster environment setup:** now ~643-716s (~10.7-11.9 min, see
  Part A) — under budget, down from Session 17's confirmed ~2083s.
- **Product walkthrough:** a real, continuous, bracketed run of the
  README's step-0 bootstrap (`migrate` → `db:seed` →
  `connectors:register-reference` → create consent purpose → create two
  Owner accounts) measured **57 seconds**. Every individual product-cycle
  HTTP call after that (consent grant, DSAR erasure submit, two admin
  logins, verify-identity, approve-erasure) returned in well under a
  second each, checked directly via curl. An attempt to re-time the DSAR's
  async completion (the worker/reference-connector round trip) was
  **discarded, not reported**, because this session's own multi-turn
  tool-call gaps between the approve-erasure call and the first
  completion poll contaminated the interval — the same discipline Session
  17 used when it rejected Session 16's own flawed ~13-hour build-time
  anomaly rather than record it as real. Session 16's clean ~46-second
  figure for that specific interval remains the trustworthy number.
  Composing the clean pieces (57s + sub-second calls + ~46s async) gives
  low single digits of minutes of real backend latency — comfortably
  under 15 minutes on that basis, though a real human's click-through time
  was not stopwatch-measured against a real browser this session (R-08 is
  exactly why) — stated as a reasoned characterisation, not a claim this
  session watched a real person complete it.

`00-project-brief.md`'s Success Metric #1 now states these three things
separately. Nothing else in the brief changed; the demo-instance decision
and GDPR-only/single-tenant scope are untouched.

## What was explicitly NOT done this session, and why

1. **R-01, R-02, R-05, R-06 — untouched, per ground rules.**
2. **No ADR reopened.** No GDPR-only/single-tenant/public-demo scope
   touched.
3. **R-08's underlying hang — still not fixed.** One genuine, well-reasoned
   attempt made and reported honestly as unresolved; see Part B and the
   risk register for the narrowed remaining lead (a minimal standalone
   Playwright script, no Pest/Laravel, to isolate whether this is
   Playwright-in-this-Docker/WSL2-environment generally).
4. **A registry-hosted prebuilt image for R-07 — still not attempted**
   (would remove the build cost entirely for a self-hoster, needs a
   registry + CI publishing step, out of scope again this session — but
   no longer urgent now that the direct-build path is under budget).
5. **One final, single, unbroken cold-clone timing run of the exact
   committed Dockerfile — not completed**, blocked by the GitHub rate
   limit described in Part A. The fix's correctness is confirmed
   functionally (165/165 tests via live-patch); only the single-run timing
   number is a composed estimate rather than one atomic measurement.

## Files created or changed

**Infrastructure (R-07):** `docker/Dockerfile` (multi-stage: `deps` →
`runtime` / `test-deps` → `test`), `docker-compose.yml` (`app`'s build
`target: runtime`; new `app-e2e` service, profile-gated, `target: test`,
`shm_size: "1gb"`; `worker`'s comment updated), `CONTRIBUTING.md` (new
`app-e2e` invocation for `tests/Browser/`), `.github/workflows/ci.yml`
(explanatory comment only, no functional change).

**Docs:** `docs/project-memory/09-decision-log.md` (new entry: Success
Metric #1's revision, with full reasoning and the real numbers behind
it), `docs/project-memory/00-project-brief.md` (Success Metric #1 reworded
into three separate claims), `docs/project-memory/10-risk-register.md`
(R-07 closed with full evidence and an honest gap noted; R-08 updated
with this session's attempt, corrected mechanism theory, and narrowed
remaining lead), this file.

**No application code changed.** No migrations. No new dependencies.

## Validation performed

- `docker compose exec app composer test` → **165/165 passed**, run
  against a live-patched container (pre-fix image + `sockets`/`pcntl`
  installed via `docker-php-ext-install` directly, no package
  re-download) after the rate limit blocked a fresh full image build —
  see Part A for the full honest accounting of why, and why this
  substitute is evidentially sound (the same extensions, same composer
  logic, only the *path* to getting them installed differs).
- `composer lint` (Pint) → clean, 152 files.
- `composer analyse` (Larastan level 8) → no errors.
- `npm run lint` (ESLint) → clean.
- `docs/architecture/openapi.yaml` validated with `openapi_spec_validator`
  (throwaway `python:3.12-slim` container) → **OK**, unchanged (no HTTP
  surface touched this session).
- `composer test:e2e` / `vendor/bin/pest tests/Browser` (via the new
  `app-e2e` service, with the `shm_size` fix applied) → **still hangs**,
  same signature as Session 17 — see Part B for the full attempt and
  evidence.
- **A genuinely cold Docker build was re-measured end-to-end** on the
  intermediate (pre-extension-fix) Dockerfile: 643s. The extension-fix
  compile step was separately, directly timed: 73s. See Part A for why a
  single unbroken measurement of the exact final Dockerfile was not
  completed this session.
- A real, bracketed timing of the README's product walkthrough was run
  against the freshly rebuilt runtime stack — see Part C.
- **Pushed:** Session 17's commit was pushed at the very start of this
  session. This session's own work is committed and pushed after this
  handoff is written (see the commit that follows).

## Open questions and risks

- **R-01 — unchanged, still open.**
- **R-02, R-04, R-05, R-06 — unchanged, still closed.**
- **R-07 — closed this session.** Root cause fixed (Playwright/Chromium
  removed from the default runtime image), re-measured (643s, down from
  2083s, ~69% reduction), a real regression found and fixed along the way
  (165/165 tests confirm it), with one honestly-flagged gap: the exact
  final Dockerfile's cold-clone time is a composed estimate (~716s), not
  yet a single atomic measurement, due to an external rate limit. Worth a
  quick confirmation in a future session, not urgent.
- **R-08 — still open.** One genuine, evidence-based attempt made this
  session (`shm_size` increase, after ruling out the two angles the brief
  suggested with direct investigation, not assumption); did not resolve
  it. The two most commonly cited fixes for this failure class are now
  both ruled out. The remaining lead (a minimal Pest/Laravel-free
  Playwright repro) needs a genuinely different investigation, not another
  Docker-config-flag variation.
- **Optional/stretch items (retention UI, RoPA export button, policy
  management UI, audit log view) — all still open, all API/CLI-only**,
  unchanged from prior sessions.

## Next recommended session

**Two independent leads, either order, both lower-cost than this
session's:**

1. **The minimal Playwright repro for R-08**, now the clearly narrowed
   next step (Part B ruled out the two easier angles). Write a standalone
   Node script (no Pest, no Laravel, no `pest-plugin-browser`) inside the
   `app-e2e` image that just does `playwright.chromium.launch()` and
   navigates to a real URL. If it hangs too, the issue is
   Playwright-in-this-Docker/WSL2-environment generally (a platform
   constraint, possibly worth documenting as permanent rather than
   chasing further). If it works, the issue is specific to how
   `pest-plugin-browser` launches `playwright run-server`, and the search
   narrows to that one layer.
2. **A quick confirmation re-measurement of R-07's exact final Dockerfile**
   once enough time has passed since this session's rate-limit trigger —
   a single `docker builder prune -af` + `docker compose up --build -d`
   cycle would turn the ~716s estimate into one atomic number. Low
   priority given the margin under budget either way.

- Inputs required: this file, `docs/project-memory/10-risk-register.md`
  (R-01/R-07/R-08).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 18
complete and pushed.

**Current stack:** unchanged since Session 13 — Laravel 12, Vue 3/Inertia,
PostgreSQL, Redis, S3-compatible storage, `barryvdh/laravel-dompdf`,
`pestphp/pest-plugin-browser`. No new dependencies this session — R-07's
fix is purely a Dockerfile/Compose restructuring, no new packages.

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0-17 remain in force. No ADR touched or reopened this session.

**Implementation state:**
- Done: everything from Session 17, plus: R-07 closed (multi-stage
  Dockerfile, `app-e2e` Compose service, 643s/~716s build time, down from
  2083s); a corrected understanding of R-08's mechanism (the `sh -c`
  layer isn't in the CDP fd-inheritance path after all) plus one
  concluded negative result (`shm_size` doesn't fix it); Success Metric
  #1 reworded into three separate, honestly measurable claims.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) R-01 — DB-level grant revocation for
  the audit log unbuilt; (2) R-07 — closed, but the exact final
  Dockerfile's cold-clone time is a composed estimate (~716s), not yet
  one atomic measurement (low priority, see "Next recommended session");
  (3) R-08 — the browser/E2E suite still hangs; a minimal Playwright-only
  repro (no Pest/Laravel) is the clearly narrowed next step; a manual
  walkthrough (Session 17) is the current fallback for re-confirming
  button-driven claims; (4) no password reset flow; (5) retention/RoPA/
  policy/audit-log management UIs remain API/CLI-only.
- Not started: a registry-hosted prebuilt image (no longer urgent for
  R-07 given the direct build is now under budget), a fix or workaround
  for R-08's underlying hang, connector secret rotation, HTTP
  connector-management (deliberately deferred), email/notification
  delivery, password reset, the `RetentionPolicyController::store`
  duplicate-active-policy validation gap (Session 12 finding, still
  open), retention/RoPA/policy/audit-log management UIs, the public demo
  instance's isolated infrastructure/spend cap/scheduled reset (the one
  remaining unchecked MVP item).

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — this session introduced no new
architectural pattern or dependency.

**Task for next session (single objective):** the minimal Playwright-only
repro for R-08 (see "Next recommended session" above) is the clearest,
most narrowed next step across the two remaining open risks. R-01 remains
open and available as an alternative if that doesn't fit.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/10-risk-register.md` (R-01/R-07/R-08)

**Ground rules:** Do not change the stack. Do not reopen any existing ADR.
R-01/R-08 remain open — do not fold a fix in silently. R-02/R-04/R-05/R-06/
R-07 are closed — do not reopen without a genuine new finding.
