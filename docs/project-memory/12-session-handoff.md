# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 17 — Close two Session 16 loose ends
  (R-07's real number, R-08's reproducibility), then close R-04**
- Objective, in priority order per this session's brief: (A) determine
  whether Session 16's duplicate-build fix also helps a genuinely
  first-ever cold clone, and get a real cold-clone build-time number if at
  all feasible; (B) determine whether Session 16's browser-suite hang was
  actually sandbox-specific by testing on a different host, and either get
  it running, or gain equivalent confidence some other honest way; (C) if
  time remained, begin R-04 (audit-log external anchor).
- Status: **complete, not yet pushed.** All three parts were completed.
  Part A produced a real, directly-measured cold-clone number (not just a
  layer-level estimate). Part B's reproducibility check came back
  positive — the hang **does** reproduce on a second, unrelated host — but
  a real manual walkthrough was substituted to re-confirm the specific
  backend claims Sessions 14/15 made, with an explicit, honest scope limit
  on what that walkthrough cannot prove. R-04 was fully implemented and
  proven with a real attack-simulation test, not just an assertion.
  165/165 Feature tests pass (160 carried over + 5 new, all in
  `AuditChainAnchorTest.php`), Pint clean (152 files), Larastan level 8
  clean, ESLint clean, `docs/architecture/openapi.yaml` re-validated
  (`openapi_spec_validator`, throwaway `python:3.12-slim` container) — OK,
  unchanged (R-04 added no new HTTP surface).

## Part A — R-07: does the duplicate-build fix help the first-ever cold clone, and what does a real cold-clone build actually cost?

**Short answer to both halves of the question, stated plainly up front:**
yes, the duplicate-build fix helps the first-ever cold clone (not only
repeat rebuilds) — and a real, directly-measured first-ever cold clone on
this session's host took **2083 seconds (~34.7 minutes)**, more than
double Success Metric #1's 900-second budget on its own.

### Which of Session 16's two fixes helps which scenario

Session 16 shipped two independent fixes to `docker/Dockerfile` and
`docker-compose.yml`:

1. **Layer reordering** — the `npm ci && npx playwright install
   --with-deps chromium` step now runs *before* `COPY . .`, so an ordinary
   code change doesn't invalidate that layer's cache.
2. **Duplicate-build elimination** — `worker` no longer declares its own
   `build:` block; it reuses `app`'s already-built, tagged image.

These have **different relationships to a first-ever cold clone**:

- **Layer reordering helps only repeat builds.** A first-ever build starts
  with an empty cache by definition — there is nothing to invalidate
  either way, so reordering contributes nothing to the first build's total
  time. It remains valuable for every build *after* the first (which is
  the common case in practice, and the case Session 16 measured at 165s).
- **Duplicate-build elimination helps both repeat builds *and* the
  first-ever cold build.** The duplication was structural — two
  independent `build:` blocks with no shared cache key between them — not
  a function of cache state. Session 16 directly observed the pre-fix
  code running the identical ~15-18 minute Playwright/Chromium layer
  *twice, in parallel*, specifically **on a cold cache** (two separate
  `RUN npm ci && ...` steps in the same build log, each paying the full
  download cost independently). That observation was already evidence
  this fix mattered for the first build, not only the tenth. This
  session's own from-scratch build (below) confirms it directly: the build
  log shows zero build steps for `worker` — it went straight from `app`'s
  finished image to `Container privacy-forge-worker-1 Creating`, with no
  second Playwright/Chromium download anywhere in the log.

This session did **not** revert the fix and re-measure the old buggy
behaviour side-by-side to get an exact "with vs. without" delta — that
would mean deliberately reintroducing a closed defect purely to benchmark
it, which felt like the wrong trade against the structural reasoning above
plus Session 16's own direct log observation, already strong evidence on
its own. If a precise delta ever matters, it would need to be measured by
temporarily reverting `docker-compose.yml`'s `worker` service, not by
guessing.

### The real cold-clone number

Session 16 attempted this exact measurement and could not get a clean
result (a `docker builder prune -f` followed by `docker compose up
--build` showed a ~13-hour anomalous gap in that sandbox, honestly reported
as not a real measurement). This session repeated the attempt on a
**genuinely different host** (Windows 11 + Docker Desktop, WSL2 backend,
vs. Session 16's Linux sandbox) and got a clean result:

1. `docker compose down` (stack stopped, volumes preserved).
2. `docker rmi -f privacy-forge-app:latest privacy-forge-worker:latest
   privacy-forge-frontend:latest` (all three project images removed).
3. `docker builder prune -af` — confirmed **0B build cache, 0 project
   images** remaining before starting (`docker system df` checked
   directly, not assumed).
4. A single bracketed `docker compose up --build -d`, timestamped with
   `date +%s` immediately before and after.

**Result: 2083 seconds (~34.7 minutes) wall-clock**, start to all six
containers (`app`, `frontend`, `worker`, `postgres`, `redis`, `minio`)
reporting healthy/running. This is the number Success Metric #1's
15-minute budget actually depends on — not the 165-second repeat-rebuild
number, which was never the right number for this question and was never
claimed to be by Session 16 either (its own handoff was explicit that the
first-build cost was separate and unmeasured).

BuildKit's own step timings, recovered from the build log, give a partial
breakdown (the very first ~1250s of steps — initial `apt-get`, nodesource
setup, first `composer install`, and the separate `frontend` image's own
`npm install` — fell outside the retained log tail and were not broken
down individually this session, though they're included in the 2083s
total):

| Step | Time |
|---|---|
| `npm ci && npx playwright install --with-deps chromium` | 468.0s (~7.8 min) |
| `COPY . .` | 7.1s |
| second `composer install` (post-`COPY . .`) | 17.2s |
| exporting layers | 209.2s |
| unpacking final image | 138.5s |
| **image export + unpack, total** | **348.7s (~5.8 min)** |
| (remaining steps, not broken down) | ~1266s |
| **Total** | **2083s** |

Two things worth stating honestly:

1. **The Playwright/Chromium layer took less time this session (468s)
   than Session 16 measured on its own host (~1069s).** Both numbers are
   real, directly measured — the difference is environment/network
   variance (this session's host, this session's network path to the
   Playwright CDN, at this specific time), not either session's number
   being wrong. Neither should be read as a universal constant.
2. **Image export/unpack (348.7s) is a previously unmeasured, unreported
   cost roughly as large as the Chromium download itself.** This is a
   containerd-image-store operation (exporting the ~3.3GB `app` image's
   layers, then unpacking them) — not something either of Session 16's
   two fixes touches, and not mentioned in Session 16's own handoff. It's
   plausibly worse on this specific host (Windows Docker Desktop/WSL2)
   than on a native Linux daemon, though this session did not attempt to
   isolate that variable (would need a second Linux host to compare
   against, out of scope here).

**Net honest statement for R-07:** the two fixes Session 16 shipped are
real, correctly structural (not accidental cache artifacts), and both help
a first-ever cold clone or at minimum don't hurt it — but they were never
going to bring a genuinely first-ever build under the 15-minute budget on
their own, because the layer they target (~7-18 minutes depending on
network conditions) is only part of the real cost. A real, direct
measurement now exists — 2083s — and it exceeds the budget by more than
2x. The residual risk Session 16 flagged as "unavoidable without a
registry-hosted prebuilt image" is now a confirmed number, not a
suspicion.

## Part B — R-08: does the browser-suite hang reproduce on a different host?

**Short answer: yes.** Session 16 assessed the hang as "environment issue
specific to this sandboxed session, not a product regression," and
explicitly flagged that the next session should check reproducibility on
a different host before trusting that assessment. This session did
exactly that, on a genuinely unrelated host (Windows 11 + Docker
Desktop/WSL2, vs. Session 16's Linux sandbox) — **and the hang reproduced
with the same signature.** That specific hypothesis ("this was just
Session 16's sandbox") is now rejected by direct evidence, not merely
still unconfirmed.

### What was tried, and what was found

Before retrying blindly, this session first reasoned about what Session
16 actually changed that could plausibly affect the browser suite: Docker
layer reordering (could affect whether/how Chromium is available inside
the container at test time), the new `ReferenceConnectorWebhookController`
route (a port/routing conflict), and the new seeder/connector-registration
logic running during test setup. None of these held up on inspection —
the new route lives on the same port/process as everything else (no new
port), the seeder only inserts five rows in ~120ms, and there is no
`.dockerignore` clobbering concern on this host specifically because this
host's `node_modules`/`vendor` directories happened to be empty (checked
directly) — none of Session 16's changes gave a live lead.

`docker compose exec -T app vendor/bin/pest tests/Browser -v` was run
directly (bypassing Composer's 900s timeout, as Session 16 also did). This
time, the browser suite's own database bootstrap (`RefreshDatabase`) was
first confirmed to have reset the shared dev database exactly as expected
— an important note for the next section, since it meant the manual
walkthrough's seed data had to be redone afterward.

The process tree was inspected directly via `/proc/<pid>/cmdline` (no
`ps` binary in this minimal image, matching Session 16's own approach) and
showed a **fuller, healthier-looking process tree than Session 16
reported**: not just a resident Chromium process, but a real zygote, a GPU
process, a network-service utility process, and two renderer processes —
genuine multi-process Chromium startup, further than Session 16 described
reaching. And yet: **zero `GET`/`POST` navigation requests ever appeared
in the app container's request log**, checked via `docker compose logs
app` across 12+ minutes. The browser process's own accumulated CPU time
(`/proc/<pid>/stat`, fields 14/15 — `utime`/`stime`) grew by only ~8 clock
ticks (~0.08s) across several minutes of real wall-clock time — a stalled
handshake, not a slow-but-working test, exactly matching Session 16's own
characterisation on a completely different host.

**A concrete, plausible (not proven) mechanism** was identified this
session by reading the actual launch chain from `/proc`: `pest` (PHP) →
`sh -c './node_modules/.bin/playwright run-server ... --mode
launchServer'` → `node` → `chrome-headless-shell`, with Chromium launched
using `--remote-debugging-pipe` (CDP over inherited file descriptors 3/4,
not a TCP/WebSocket port). The intermediate `sh -c` shell in that chain is
a well-documented place where exact fd 3/4 inheritance for Playwright's
pipe transport can silently break across process layers — which would
produce exactly this symptom: a real Chromium process launches
successfully, but the CDP handshake never completes because neither side
is actually connected to the file descriptors the other expects. This is
Playwright/`pestphp/pest-plugin-browser`'s own launch behaviour (visible
in `node_modules`, not this repository's code), not something any
application-code session — including Session 16 — introduced. This
session did **not** attempt to patch vendor code or force an alternative
CDP transport to confirm or fix this; that would be a materially larger
undertaking than this session's scope, and is left as the lead for a
future session (see the risk register's R-08 entry for the specific next
step suggested).

The hung process and its full child tree (`pest`, `node`,
`chrome-headless-shell` ×5) were killed cleanly before moving on, so no
stray processes were left behind for the next `docker compose exec`, the
same housekeeping issue Session 16 flagged.

### The manual walkthrough — what was actually re-confirmed, and what wasn't

Since the automated suite could not be run to completion, this session
built the same kind of substitute confidence used for R-06's manual
verification last session: real HTTP calls, with real cookies/CSRF/
sessions, against the live docker-compose stack — driving the exact same
endpoints the admin dashboard's buttons call, not `actingAs()`, not a
shortcut.

**What was verified, one claim at a time, against a freshly reseeded
database:**

| Claim (from Sessions 14/15) | How it was re-checked this session | Result |
|---|---|---|
| Real staff login works | `GET /login` for a real CSRF cookie, decoded, `POST /login` with `X-XSRF-TOKEN` and real credentials | `200`, real session cookie issued |
| The "Verify identity" button's endpoint works | `POST /api/v1/admin/dsar/{id}/verify-identity`, authenticated as the real logged-in admin | `200`, DSAR moved to `in_progress` |
| ADR-0007's separation-of-duties denial renders as a real ABAC decision | Same admin immediately calls `approve-erasure` on the same DSAR | Real `403`, `"The dsar.erasure.approve policy denied this request."` |
| The "Approve erasure" button's endpoint works for a *different* admin | Second admin logs in for real, calls `approve-erasure` | `200` |
| The DSAR reaches real completion via the async worker/reference-connector round trip (R-06) | Queried `DsarRequest::find($id)->status` directly against the database after the above | `complete` |
| The buttons' click handlers call these exact endpoints | Read `resources/js/Pages/AdminDsarQueue.vue` directly | Confirmed: `verifyIdentity()`/`approveErasure()` call `performAction(dsar, 'verify-identity')`/`'approve-erasure'`, matching the routes above exactly |

**What this walkthrough does not and cannot confirm, stated as plainly as
the task asked for:** that a real browser actually renders these buttons,
and that a real mouse click actually fires those handlers. This is not a
minor caveat — it was checked directly and confirmed to be a real gap:
`GET /admin/dsar`'s server-rendered HTML was fetched and its Inertia
`data-page` JSON payload inspected directly. It carries only
`errors`/`auth`/`csrfToken` — **no DSAR queue data at all**. The queue
list (and therefore whether "Verify identity"/"Approve erasure" buttons
even appear for a given row) is fetched client-side, after Vue mounts and
runs its own `fetch()` call. A curl-only check structurally cannot observe
that — it would need a real browser executing real JavaScript against a
real DOM, which is exactly what the hung suite would have proven and
exactly the one part of Sessions 14/15's claims this session leaves
genuinely unconfirmed.

### Is R-08 resolved, worked around, or still open? (asked to be unambiguous)

**Still open, not resolved — but no longer ambiguous, and worked around
for this session's purposes.** The suite does not run; the hypothesis
that this was sandbox-specific is now rejected by evidence rather than
merely untested; a real, scoped manual walkthrough substitutes for it with
an explicit, honest account of what it does and doesn't cover (backend
contract: re-confirmed; real DOM click-through: not confirmed, not
claimed). Success Metric #1's staff/admin claim from Session 15 should be
read accordingly: the mechanisms those buttons depend on are freshly
re-verified against a live, post-Session-16 stack; the specific claim "a
real button click fires them" rests on Session 15's original browser test
run and has not been re-verified since Session 16's infrastructure
changes landed.

## R-04 — audit-log periodic external anchor (ADR-0003), closed this session

With Parts A and B done, this session had time for R-04, the next
recommended priority per Session 16's own handoff, and completed it.

### What was built

`app/Services/AuditLogger.php` gained two methods:

- **`anchorChain()`** — writes the current chain head (latest entry's
  `sequence` + `entry_hash`) to a new, uniquely-keyed object on the `s3`
  disk (`audit-anchors/{sequence}.json`). This disk is the **same external
  object storage export bundles already use** (Session 8/`ExportBundleAssembler`)
  — no new infrastructure dependency, no new architectural pattern, no
  third new technology against the 2-tech learning-budget cap. The anchor
  is deliberately **not** stored in this application's own Postgres
  database: ADR-0003's whole point is protecting against an attacker who
  has *already* compromised the database and can edit any row — an anchor
  living in that same database would be exactly as editable by that
  attacker, which would defeat the purpose entirely. `anchorChain()` never
  issues a write to an already-anchored sequence's key, so anchors are
  append-only by construction (an accepted limitation, matching ADR-0003's
  own stated one: this proves tamper *evidence*, not tamper
  *impossibility* — a real deployment hardening this further would want
  object-lock/versioning at the bucket level, not attempted this session).
- **`verifyAnchors()`** — replays every anchor ever written and confirms
  the sequence it names still has the *same* `entry_hash` in the live
  database today. This is the check `verifyChain()` alone cannot do: an
  attacker who edits an old entry and recomputes every subsequent
  `prev_hash`/`entry_hash` (a full chain rewrite) makes `verifyChain()`
  pass again, because that method only replays the chain as it currently
  stands — it has no memory of what the chain looked like *before* the
  rewrite. Anchors are that memory, held outside the database the
  attacker compromised.

Two new commands:

- **`app/Console/Commands/AnchorAuditChainCommand.php`** (`audit:anchor-chain`)
  — registered on the scheduler (`routes/console.php`, hourly). ADR-0003's
  Consequences section requires that anchor unavailability "must trigger
  an alert, not fail silently" — this command never swallows a failure. A
  storage write failure or any unexpected exception both log at
  `Log::critical` (this project's existing convention for a fault an
  operator must see — matching `PolicyEvaluator`'s fail-closed logging)
  and exit non-zero. A successful anchor also writes its own
  `audit.chain.anchored` audit-log entry, matching
  `ExecuteRetentionPoliciesCommand`'s existing convention of self-logging
  scheduled system actions.
- **`app/Console/Commands/VerifyAuditChainCommand.php`** (`audit:verify-chain`)
  — the runbook item ADR-0003's Consequences section explicitly calls
  for ("chain verification becomes a documented runbook item ... it must
  be run routinely, and its result must be visible"). Checks both
  `verifyChain()` and `verifyAnchors()`, non-zero exit if either fails.

**Cadence choice: hourly.** Nothing in this project's threat model
(single-tenant, self-hosted, no stated SLA on detection latency) calls for
a tighter cadence; hourly bounds the "unanchored window" (entries written
since the last anchor, which a rewrite could still cover without
`verifyAnchors()` catching it) to at most an hour, and `anchorChain()` is
idempotent when the chain hasn't grown, so running it more often than the
chain grows is harmless.

### Proof, not assertion — the specific evidence requested

`tests/Feature/AuditChainAnchorTest.php` (5 tests):

1. **The core proof**: builds a real 3-entry chain via `AuditLogger::record()`,
   anchors it (`Storage::fake('s3')`), then simulates a genuinely
   privileged DB-access attacker — bypassing `AuditLogEntry`'s append-only
   guard via `DB::table('audit_log_entries')->update()` (the same
   technique `ConsentCaptureTest.php` already uses to simulate DB-level
   tampering) — tampering with the *first* entry's `action` and correctly
   recomputing every subsequent `prev_hash`/`entry_hash` using the exact
   same hash formula `AuditLogger` uses, so the rewritten chain is
   internally consistent. Asserts `verifyChain()` returns `valid: true`
   (the rewrite fools it — this is the exact gap ADR-0003 describes) and
   `verifyAnchors()` returns `valid: false` with `brokenAtSequence`
   pointing at the anchored (latest) sequence, because that anchor was
   written *before* the rewrite and still holds the original hash.
   (First draft of this test asserted `brokenAtSequence` against the
   *tampered* entry's sequence instead of the *anchored* entry's — wrong,
   since `verifyAnchors()` only has anchors to check against, and there
   was only one, at the latest sequence; caught by the test actually
   failing on first run, not by inspection, and fixed.)
2. `anchorChain()` on a fresh instance reports `no_entries` rather than
   anchoring nothing silently.
3. `anchorChain()` is idempotent — anchoring an unchanged chain twice
   writes identical content to the same key, confirmed by asserting
   exactly one file exists afterward.
4. `AnchorAuditChainCommand` anchors successfully and records its own
   `audit.chain.anchored` entry.
5. `AnchorAuditChainCommand` alerts rather than fails silently — a bare
   Storage stub is swapped in via `Storage::set('s3', ...)` to force
   `put()` to return `false`, and the test asserts `Log::critical` was
   called and the command exited non-zero.

All five pass. 165/165 Feature tests total (160 carried over + these 5).
Pint clean (152 files). Larastan level 8 clean (the return-shape docblocks
needed care: `anchorChain()`'s return array uses uniform, always-present,
nullable keys rather than PHPStan's optional-key (`?:`) shape syntax,
specifically to avoid "offset may not exist" errors after narrowing on
`anchored` — matching this codebase's existing pattern, seen in
`ExportBundleController::raw()`, of explicitly null-checking
`Storage::disk()->get()`'s nullable return rather than assuming it's
always a string). ESLint clean (no frontend changes). OpenAPI unchanged
and re-validated — these commands are CLI-only, deliberately not part of
this application's documented HTTP API contract, the same reasoning
Session 16 used for the reference connector's own webhook route.

`01-scope-and-non-goals.md`'s MVP checklist item for "Tamper-evident audit
log (hash chain, periodic anchor)" is now checked — 8 of 9 MVP boundary
items are complete; only the public demo instance itself remains.

## MVP boundary checklist — restated

**8 of 9, up from 7 of 9.** R-04 (audit-log anchor) closed this session.
The one remaining unchecked item is the public demo instance (isolated
infrastructure, spend cap, scheduled reset) — its seeder half was already
closed at Session 16 (R-02).

## What was explicitly NOT done this session, and why

1. **R-01, R-02, R-05, R-06 — untouched, per ground rules.** All remain
   closed/open exactly as Session 16 left them. Running `composer test`
   and the manual walkthrough this session did re-exercise R-02's seeder
   and R-06's reference connector as a side effect of normal use (they're
   load-bearing for any DSAR flow to work at all), but nothing about their
   code or tests changed.
2. **No ADR reopened.** ADR-0003's Decision, options, and trade-offs are
   unchanged — R-04 is exactly the anchoring layer the ADR already called
   for, implemented for the first time, not redesigned.
3. **R-08's underlying hang — not fixed.** Diagnosed further (a
   specific, plausible mechanism identified) and its reproducibility
   confirmed across two hosts, but not patched — see the risk register's
   suggested next step (a minimal standalone Playwright-in-Docker
   reproduction, no Pest/Laravel involved, to isolate whether Pest's own
   process launching is implicated).
4. **A registry-hosted prebuilt image for R-07's first-build cost — still
   not attempted** (needs a registry + CI publishing step, out of scope
   two sessions running now).
5. **GDPR-only/single-tenant/public-demo scope — untouched.**
6. **Optional/stretch items (retention policy UI, RoPA export button,
   policy management UI, audit log query view) — untouched, still
   API-only.** Not this session's priority; R-04's own `audit:verify-chain`
   command is a CLI runbook tool, deliberately not a UI, matching how
   retention/RoPA/policy management are already CLI/API-only.

## Files created or changed

**Backend (R-04):** `app/Services/AuditLogger.php` (new `anchorChain()`/
`verifyAnchors()`, `ANCHOR_DISK`/`ANCHOR_PATH_PREFIX` constants),
`app/Console/Commands/AnchorAuditChainCommand.php` (new),
`app/Console/Commands/VerifyAuditChainCommand.php` (new),
`routes/console.php` (hourly schedule registration for the anchor job,
header comment updated).

**Testing:** `tests/Feature/AuditChainAnchorTest.php` (new — 5 tests,
including the full-chain-rewrite attack simulation).

**Docs:** `docs/project-memory/10-risk-register.md` (R-07 re-measured with
a real cold-clone number and the Part A reasoning; R-08 upgraded from
"unknown, check on a different host" to "confirmed to reproduce, likely
structural, manual-walkthrough fallback established"; R-04 closed with
full evidence), `docs/project-memory/01-scope-and-non-goals.md` (audit-log
checklist item checked, 8/9 restated, a stale "no seeders directory"
sentence from Session 13 corrected), this file.

**Infrastructure:** none changed this session — R-07's Dockerfile/compose
fixes are Session 16's; this session only re-measured them on a different
host.

## Validation performed

- `docker compose exec app composer test` → **165/165 passed** (160
  carried over + 5 new), re-run after fixing a bug in the anchor test's
  own first draft (a wrong sequence-number assertion, caught by the test
  actually failing, not by review) — clean on the second run.
- `composer lint` (Pint) → clean, 152 files.
- `composer analyse` (Larastan level 8) → no errors.
- `npm run lint` (ESLint) → clean.
- `docs/architecture/openapi.yaml` validated with `openapi_spec_validator`
  (throwaway `python:3.12-slim` container) → **OK**, unchanged.
- `composer test:e2e` / `vendor/bin/pest tests/Browser -v` → **hung**,
  same as Session 16; see Part B above for the full investigation,
  process-tree evidence, and the manual-walkthrough substitute.
- **A genuinely cold Docker build was measured end-to-end this session**
  (2083s) — see Part A above.
- **A real manual walkthrough was run against the live docker-compose
  stack** re-confirming Sessions 14/15's login and admin-button backend
  claims — see Part B above.
- No rollback-parity concern — R-04 added no migrations (anchors live on
  external object storage, not in a new table).
- **Not yet pushed** — awaiting confirmation before push, per this
  project's established pattern.

## Open questions and risks

- **R-01 — unchanged, still open.**
- **R-02, R-05, R-06 — unchanged, still closed** (Sessions 14/16).
- **R-04 — closed this session**, with a real attack-simulation test as
  evidence, not an assertion.
- **R-07 — still open.** Both of Session 16's fixes re-confirmed correct
  and structural (not cache-state-dependent); a real first-ever cold-clone
  number now exists (2083s) and confirms the residual risk to Success
  Metric #1's 15-minute budget is real, not merely theoretical. A
  registry-hosted prebuilt image remains the only identified full fix,
  still unattempted.
- **R-08 — still open, no longer ambiguous.** Confirmed to reproduce on a
  second, unrelated host with an identical signature — the
  "sandbox-specific" hypothesis is rejected. A specific, plausible (not
  proven) mechanism was identified (CDP pipe-transport fd inheritance
  across an intermediate shell layer in Playwright's own launch chain). A
  manual walkthrough substitutes for the automated proof this session,
  with an explicit, checked scope limit: it re-confirms the backend
  contract behind Sessions 14/15's login/button claims, but cannot and
  does not confirm real browser DOM rendering or real click-driven
  interaction.
- **Optional/stretch items (retention UI, RoPA export button, policy
  management UI, audit log view) — all still open, all API/CLI-only**,
  unchanged from Session 15/16.

## Next recommended session

**Two independent, lower-cost leads, either order:**

1. **Try to actually root-cause or work around R-08.** The suggested next
   step: reproduce the CDP-pipe hang with a *minimal* standalone
   Playwright script inside the same container image (no Pest, no
   Laravel) — if it *also* hangs, the issue is Playwright-in-this-Docker-
   setup generally, and the fix (if one exists) would be forcing a
   non-pipe CDP transport, or documenting this as a known, permanent
   constraint of running headless Chromium in this specific container
   base image. If the minimal script *doesn't* hang, the issue is
   specific to how `pest-plugin-browser` launches `playwright run-server`,
   narrowing the search considerably.
2. **A registry-hosted prebuilt image for R-07.** Would need a container
   registry and a CI publishing step (GitHub Actions building and pushing
   `privacy-forge-app` on merge to `main`) — genuinely out of a single
   session's scope to set up *and* verify, but now clearly justified: a
   34.7-minute first build is a real, measured barrier to Success Metric
   #1 for any stranger cloning this repository fresh, not a theoretical
   one.

Either could reasonably be the sole focus of the next session; both are
independent of R-01 (the other remaining open risk) and don't block it.

- Inputs required: this file, `docs/project-memory/10-risk-register.md`
  (R-01/R-07/R-08).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 17
complete, **not yet pushed** (awaiting confirmation).

**Current stack:** unchanged since Session 13 — Laravel 12, Vue 3/Inertia,
PostgreSQL, Redis, S3-compatible storage, `barryvdh/laravel-dompdf`,
`pestphp/pest-plugin-browser`. No new dependencies this session — R-04
reuses the existing `s3` filesystem disk and Laravel's own scheduler.

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0-16 remain in force. No ADR touched or reopened this session —
R-04 is ADR-0003's own already-specified anchoring layer, implemented for
the first time.

**Implementation state:**
- Done: everything from Session 16, plus: a real periodic audit-log
  anchor (`AuditLogger::anchorChain()`/`verifyAnchors()`, scheduled hourly,
  proven against a real full-chain-rewrite attack simulation); a real,
  directly-measured first-ever cold-Docker-build number (2083s); a
  confirmed-not-sandbox-specific diagnosis of the browser-suite hang, with
  a real manual walkthrough substituting for it this session.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) R-01 — DB-level grant revocation for
  the audit log unbuilt; (2) R-07 — a genuinely first-ever cold clone
  takes ~34.7 minutes on this session's host, over 2x Success Metric #1's
  budget, and no registry-hosted prebuilt image exists yet; (3) R-08 — the
  browser/E2E suite hangs on at least two independent hosts, cause
  narrowed but not fixed; a manual walkthrough is the current fallback for
  re-confirming button-driven claims after future changes; (4) no password
  reset flow; (5) retention/RoPA/policy/audit-log management UIs remain
  API/CLI-only.
- Not started: a registry-hosted prebuilt image (R-07), a fix or
  workaround for R-08's underlying CDP-pipe hang, connector secret
  rotation, HTTP connector-management (deliberately deferred), email/
  notification delivery, password reset, the
  `RetentionPolicyController::store` duplicate-active-policy validation
  gap (Session 12 finding, still open), retention/RoPA/policy/audit-log
  management UIs, the public demo instance's isolated infrastructure/spend
  cap/scheduled reset (the one remaining unchecked MVP item).

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — R-04 introduced no new
architectural pattern or dependency (it reuses the existing `s3`
filesystem disk and Laravel's scheduler, both already in use).

**Task for next session (single objective):** either root-cause/work
around R-08's CDP-pipe hang, or begin a registry-hosted prebuilt image for
R-07 — see "Next recommended session" above for the reasoning behind
both. R-01 remains open and available as a third option if neither fits.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/10-risk-register.md` (R-01/R-07/R-08)

**Ground rules:** Do not change the stack. Do not reopen any existing ADR.
R-01/R-07/R-08 remain open — do not fold a fix in silently. R-02/R-04/R-05/
R-06 are closed — do not reopen without a genuine new finding.
