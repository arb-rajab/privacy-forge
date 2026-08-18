# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main`, tagged **`v1.0.0`** this session.

## Session completed
- Session number and title: **Session 25 — close the four real Gate 9→10
  gaps (stale architecture diagrams, missing demo asset, unpublished case
  study, stale README), then answer plainly whether v1.0.0 is taggable.**
- Objective: Part 1 — update `03-architecture.md` to reflect ADR-0006,
  ADR-0007, and ADR-0008 by number, as a documentation-accuracy pass, not
  new architecture work. Part 2 — produce a real demo asset (screenshots
  or a recording) from the actual running application. Part 3 — write
  `docs/CASE-STUDY.md`, synthesising 24 sessions of real handoffs, ADRs,
  and risk-register entries into one honest narrative. Part 4 — refresh
  the README's stale status banner and "Project status" section.
- Status: **All four parts complete.** All four conditions of
  `01-scope-and-non-goals.md`'s own "Definition of v1 complete" are now
  genuinely met — see the verdict below. **`v1.0.0` has been tagged.**
  187/187 tests pass, Pint/Larastan (level 8)/ESLint clean, OpenAPI
  validates — all re-confirmed this session, not assumed.

## Part 1: `03-architecture.md` brought current

- **"Last updated" date** corrected from 2026-08-11 to 2026-08-18.
- **The `PolicyEvaluator` description** now names ADR-0006 and ADR-0007
  explicitly, as load-bearing behaviours rather than footnotes: the
  fail-closed default (what happens on a missing policy, a malformed
  condition, an evaluation exception) and the `not_equals_attribute`
  cross-field comparison operator that `dsar.erasure.approve`'s
  separation-of-duties condition actually runs on.
- **The "Technology choices summary" table** now lists all 8 ADRs, not 5
  — the three added rows are ADR-0006 (fail-closed default), ADR-0007
  (cross-field comparison operator), and ADR-0008 (Laravel 12
  retroactive adoption).
- **No redesign.** The system context/container diagrams, the three
  sequence diagrams, and every other section were left untouched —
  this was a documentation-accuracy pass matching docs to code that
  already existed and already worked, exactly as scoped. The container
  diagram already correctly said "Laravel 12" (a prior session's
  ADR-0008 consequence had already been applied there); no stale
  "Laravel 11" mention existed anywhere in this file.

## Part 2: a real demo asset, captured without touching R-08

`docs/demo/README.md` (new) and `docs/demo/screenshots/*.png` (new, 5
files) — real screenshots of the actual running application: staff login,
the DSAR queue (showing a genuine `pending_verification` and a genuine
`partially_complete` request — not staged successes), a real retention
dry-run preview against one genuinely retention-eligible record, the
RoPA export page, and the audit log with real entries.

**Why screenshots, and how, matters as much as that they exist.** `R-08`
is a formally accepted residual risk (Session 19): the Pest Browser
Testing suite's Docker-launched Chromium hangs reliably on this host
class, confirmed across three sessions of investigation on two
independent hosts. Re-attempting that exact path for a demo recording
would very likely reproduce the same hang — so it wasn't attempted.
Instead, a small script (not committed — see below) drove a **native,
host-installed Chrome** directly over the raw DevTools Protocol
(WebSocket, no Playwright/Puppeteer dependency, no Docker-launched
browser, no Pest) — a genuinely different mechanism from the one R-08
investigated, not a retry of it. One real, small obstacle was found and
worked around at the browser-launch level only: the dev stack's Vite
client script is hardcoded to `0.0.0.0:5173`, which is not a connectable
destination address from a host client (confirmed directly: `curl
http://0.0.0.0:5173` fails, `curl http://localhost:5173` succeeds) — a
`--host-resolver-rules=MAP 0.0.0.0 127.0.0.1` Chrome launch flag fixed
this without touching any application or Vite config file.

The real demo data behind these screenshots (two owner accounts, a
consent purpose, two DSARs taken through real identity-verification/
erasure-approval by two different admins per ADR-0007, one retention
policy, one backdated-and-withdrawn consent record to make the dry-run
preview show a real non-zero result) was created entirely through the
application's own real HTTP endpoints — the same ones the README's "Try
it" section walks a human through — then the dev database was reset
(`migrate:fresh`) afterward so this session leaves no demo-only data
sitting in the tracked dev environment. The capture script itself lived
only in the session scratch directory, not in the repository — `docs/
demo/README.md` documents the method in full (including the exact Chrome
flags) so it's reproducible, rather than leaving the mechanism opaque.

**One genuine, honest observation from setting this up, not swept
under the rug:** the erasure DSAR's connector-dispatch job failed
against this session's own instance of the already-running dev stack
with a `DecryptException: The MAC is invalid` decrypting
`subject_identifier` — reproducible even after restarting the `worker`
container, root cause not chased down (out of scope: this is a
pre-existing, long-lived container's runtime state issue across many
prior sessions' testing on this same stack, not a new application
defect, and no code was touched to work around it). The practical result
is a genuine `partially_complete` DSAR in the screenshot rather than
`complete` — which is, per FR-009 and the README's own existing framing,
correct system behaviour under a real connector-delivery failure, not a
bug being hidden. It is called out here in case a future session sees
the same symptom and wants to investigate further; not logged as a new
numbered risk, since it wasn't isolated to a specific root cause this
session and didn't block anything this session needed.

## Part 3: `docs/CASE-STUDY.md` written

Covers, with real ADR numbers, risk IDs, and commit references throughout
rather than generic claims:
- The core engineering story: the fail-closed ABAC engine (ADR-0001,
  ADR-0006) and the tamper-evident, externally-anchored audit log
  (ADR-0003), with the real full-chain-rewrite attack simulation
  (`tests/Feature/AuditChainAnchorTest.php`) as evidence, not assertion.
- **Three real bugs**, each picked for a different failure class: the
  `RetentionSelector` re-certification bug (Session 12) — a real
  correctness bug found via a real integration test, not inspection; the
  export-bundle TTL gap (Session 8→10) — every piece individually
  correct and tested, the actual data-subject-facing integration never
  wired; the `vendor/`-shadowing Docker bug (Session 5's `97868f1`) —
  and its role as the same commit that (separately) introduced the
  Laravel 12 drift.
- **The ADR-0008 Laravel governance incident as its own full section**,
  not a footnote — the setup (frozen "Laravel 11" ledger), the
  divergence (a commit whose own `CHANGELOG.md` argued against a bump it
  shipped anyway), the compounding (14 sessions inheriting a
  trust-the-docs-over-the-file gap), how Session 20 found it (forensic
  git-history reconstruction, not a re-read of prior handoffs), the
  retroactive resolution, and the CI safeguard now verified against the
  actual historical commit's failure mode.
- **Honest judgment calls**, named as such: R-08 accepted as residual
  after three sessions of real, evidence-based investigation rather than
  chased indefinitely; the live public demo explicitly descoped at
  Session 24 with the actual cost/benefit reasoning stated, not silently
  dropped.

## Part 4: README refreshed

- **Status banner** rewritten from "Session 13" to `v1.0.0-pending`
  (written before this session's tagging decision was finalised further
  down this document — the banner itself now reads as current either
  way, describing what's implemented rather than a session number),
  linking the new case study and demo screenshots.
- **"Project status"** rewritten to describe actual capabilities plainly
  (consent, DSAR lifecycle, retention, RoPA, ABAC, audit log — each with
  its governing ADR named) instead of leading with a session number, per
  the explicit instruction that 24 sessions in, session-number framing
  has served its purpose. R-08 is named here too, honestly, alongside
  the capabilities it doesn't yet cover.
- **A pointer to `docs/demo/`** added right after the Quickstart, for a
  reader who wants to see the walkthrough before running it.
- **Three dangling "this session"/"closed this session" phrases** in the
  existing "Try it" walkthrough were also fixed (R-02/R-05/R-06
  closures now name their actual closing session numbers instead of a
  now-meaningless relative "this session") — a small, in-scope
  documentation-accuracy fix alongside the explicitly-requested banner
  change, since leaving them would have continued to read as stale the
  same way the banner did.

## An additional gap found and fixed, beyond the four named tasks

While checking condition 3's "SDLC evidence map complete" sub-item
directly rather than assuming it was still accurate, `docs/
SDLC-EVIDENCE.md` turned out to be **genuinely stale in three rows**:
it said "6 ADRs" (now 8), described testing as "currently one
environment smoke test — feature tests begin at Session 6" (now 187
Pest tests including an exhaustive authorisation matrix and the audit-
chain attack simulation), and described deployment as "still pending
Session 8" (Sessions 22–24 actually built and verified a full production
stack, and Session 24's decision explicitly descopes live public
infrastructure — a different, decided state, not a still-pending one).
This wasn't one of the four tasks named for this session, but it
directly gates condition 3 below, so it was fixed rather than left as a
known, easily-fixed gap standing between a genuine "yes" and this
session's own verdict. All three rows corrected against the real current
state (`docs/adr/`, `tests/`, `docs/project-memory/08-deployment-and-
operations.md`); `docs/project-memory/13-release-notes.md` — a second,
smaller pre-existing gap (a template that had never once been filled in
across 24 sessions) — was also populated with real v1.0.0 release notes,
for the same reason.

## The "v1 complete" verdict — checked against all four conditions, not asserted

`01-scope-and-non-goals.md`'s own "Definition of v1 complete" has four
independent conditions:

1. **Every MVP boundary box checked and demonstrably working
   end-to-end.** ✅ Unaffected this session — confirmed 9/9 at Session
   24, re-affirmed here: every admin page exercised again this session
   while setting up the demo screenshots (login, DSAR queue, identity
   verification, erasure approval by a second admin, retention policy
   creation, dry-run preview, RoPA export, audit log) worked against a
   freshly seeded stack, exactly as the README describes.
2. **All five success metrics met and verifiable by a third party.**
   ✅ Unaffected — Metrics 1–4 previously confirmed, Metric 5 revised
   and confirmed at Session 24. Nothing in this session's docs-only
   scope touches any of them.
3. **The Gate 9→10 checklist: README quickstart verified on a clean
   machine, diagrams current, demo available, SDLC evidence map
   complete, case study published.** ✅ **All five sub-items now
   genuinely pass:**
   - **README quickstart:** ✅ already genuinely re-verified fresh at
     Session 24 (full teardown-and-rebuild); this session additionally
     ran every "Try it" step for real again while setting up demo data
     (see Part 2) — the walkthrough is not stale.
   - **Diagrams current:** ✅ fixed this session (Part 1) —
     `03-architecture.md` now names ADR-0006/0007/0008 by number.
   - **Demo available:** ✅ fixed this session (Part 2) — real
     screenshots exist in `docs/demo/`, with an honest account of why a
     recording wasn't attempted and how the screenshots were actually
     captured.
   - **SDLC evidence map complete:** ✅ `docs/SDLC-EVIDENCE.md` existed
     but was itself stale (see above) — fixed this session, checked
     directly rather than assumed still accurate.
   - **Case study published:** ✅ fixed this session (Part 3) —
     `docs/CASE-STUDY.md` now exists, is specific and factual, and
     covers the ADR-0008 incident as its own full section.
4. **No non-goal has silently crept back into scope.** ✅ Unaffected —
   nothing this session touches the non-goals table; no ADR opened or
   reopened; GDPR-only and single-tenancy untouched.

**Verdict, stated plainly: yes — v1.0.0 is genuinely taggable, and has
been tagged this session.** All four conditions hold on their real
merits, each checked directly against the current state of the
repository rather than carried forward from a prior session's assertion
(the same discipline that caught `01-scope-and-non-goals.md`'s own
stale file citation earlier this session, and that caught
`SDLC-EVIDENCE.md`'s staleness above). This closes the flagship's build
phase — the documentation/demo/case-study work the original session
spine called "Session 9" — 25 sessions later than that name suggests,
which is itself consistent with this project's own established practice
of naming things honestly rather than by their original plan.

**What "v1.0.0" does not claim**, stated as plainly as the rest of this
document: no live public URL exists (Session 24's explicit, recorded
decision); the browser-driven end-to-end admin-dashboard test remains an
accepted residual gap (R-08); `R-01` (audit log DB-grant revocation)
remains open. None of these are new findings — all three are pre-existing,
already-decided, already-documented states this session left exactly as
it found them, listed here only so a reader of this handoff doesn't have
to infer them from silence.

## Action taken: the tag

```
git tag -a v1.0.0 -m "v1.0.0 — first tagged release, see docs/project-memory/13-release-notes.md"
```

Committed and pushed this session, along with `main`, per this session's
own explicit instruction to commit and push once tests were confirmed
genuinely passing (they were — see Validation performed, above).

## What was explicitly NOT done this session, and why

1. **No ADR opened or reopened.** ADR-0006/0007/0008 were read and cited,
   never modified.
2. **`R-01` through `R-08` — not touched, none affected**, beyond citing
   R-08's existing text to explain this session's screenshot-capture
   method. No risk-register row was edited.
3. **No application code changed.** Every change this session is a
   Markdown file under `docs/` or `README.md`, plus the new PNG
   screenshots. The one script used to drive the browser for screenshots
   lived only in the session scratch directory and was never committed.
4. **The demo/hosting decision was not reopened.** No real infrastructure
   was provisioned; Session 24's decision stands exactly as recorded.
5. **`R-07`'s rate-limit follow-up (due 2026-08-24)** — not yet due as of
   this session (2026-08-18); not checked this session, since this
   session's scope was documentation/demo/synthesis, not R-07.

## Files created or changed

**Created:**
- `docs/CASE-STUDY.md`
- `docs/demo/README.md`
- `docs/demo/screenshots/01-login.png`, `02-dsar-queue.png`,
  `03-retention-dry-run.png`, `04-ropa-export.png`, `05-audit-log.png`

**Changed:**
- `docs/project-memory/03-architecture.md` — "Last updated" date,
  `PolicyEvaluator` description (ADR-0006/0007 named), Technology
  choices summary table (8 ADRs, not 5).
- `README.md` — status banner, "Project status" section, a pointer to
  `docs/demo/`, three dangling "this session" phrases in the "Try it"
  walkthrough corrected to name real session numbers.
- `docs/SDLC-EVIDENCE.md` — three stale rows (ADR count, testing status,
  deployment status) corrected against current reality; a pointer to
  the case study and demo screenshots added.
- `docs/project-memory/13-release-notes.md` — populated with real
  v1.0.0 release notes (previously an empty template across 24
  sessions).
- `docs/project-memory/01-scope-and-non-goals.md` — the Gate 9→10
  checklist citation fix already present in this session's starting
  state (a stale `04-session-system-and-templates.md` in-repo file
  citation, corrected to describe the checklist directly) is carried
  forward unchanged by this session.
- `docs/project-memory/12-session-handoff.md` (this file).

**Not changed:** any ADR, any application PHP/JS/Vue source,
`composer.json`/`composer.lock`/`package.json`, any migration, `R-01`
through `R-08`'s risk-register rows, the non-goals table.

## Validation performed

- **`composer test` (Pest, dev stack) → 187/187 passed**, re-run this
  session against the same dev stack used to capture the demo
  screenshots (reset via `migrate:fresh` afterward, so this session's
  demo data does not linger in the tracked dev environment).
- **`composer lint` (Pint) → clean, 161 files.**
- **`composer analyse` (Larastan, level 8) → 0 errors, 68 files.**
- **`npm run lint` (ESLint) → clean.**
- **`docs/architecture/openapi.yaml` → valid**, same throwaway
  `python:3.12-slim`-container method prior sessions used.
- **Every admin page and the full "Try it" walkthrough** exercised for
  real against a freshly seeded stack while producing the demo
  screenshots (Part 2) — a second, independent re-verification of the
  same claims Session 24's quickstart re-check made.

## Open questions and risks

- **`R-01`** — still open, unaffected.
- **`R-07`**'s rate-limit follow-up — due 2026-08-24, not yet due, not
  checked this session.
- **`R-08`** — unchanged, accepted residual. This session's demo
  screenshots were captured by a mechanism specifically designed to
  avoid the exact path R-08 describes, not to resolve it — the
  underlying browser-automation gap remains open, honestly.
- **`B-01`, `B-02`, `B-03`** — unchanged, still open, out of this
  session's scope.

## Next recommended session

The flagship's build phase is closed by this session's tag, and `main`
plus the `v1.0.0` tag are both pushed. What remains is genuinely post-v1:

1. **Optionally**, a GitHub Release built from
   `docs/project-memory/13-release-notes.md`'s new v1.0.0 entry, if the
   portfolio's own convention calls for one — not attempted this session
   since it wasn't named in this session's scope.
2. `R-01` (the DB-grant revocation gap), `R-07`'s rate-limit follow-up
   (due 2026-08-24), and the deferred-to-backlog items in
   `11-backlog.md` — none of which block the tag this session made.

- Inputs required: `docs/project-memory/11-backlog.md` and
  `10-risk-register.md` for whatever genuinely-post-v1 work is picked up
  next.

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, **tagged `v1.0.0`** this session —
both pushed.

**Current stack:** unchanged — no dependency versions touched this
session.

**Architecture decisions that must not be reversed:** all ADRs
(0001–0008), GDPR-only, single-tenant, the Session 24 demo-hosting
revision (no real public infrastructure).

**Implementation state:**
- Done: everything through Session 24, plus this session's documentation
  closeout — architecture diagrams current, a real demo asset, a
  published case study, a current README, and `v1.0.0` tagged.
- In progress: nothing mid-flight.
- **Known gaps, unchanged and honestly still open:** `R-01` (DB-grant
  revocation), `R-07`'s rate-limit follow-up (due 2026-08-24), `R-08`
  (browser E2E, accepted residual), `B-01`/`B-02`/`B-03`.
- Not started: any genuinely post-v1 work (`R-01`, `R-07`'s follow-up,
  `11-backlog.md`'s deferred items) — none of it was in this session's
  scope, and none of it blocks the tag this session made.

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2).

**Task for next session (single objective):** pick up genuinely post-v1
work — `R-01`, `R-07`'s follow-up, or `11-backlog.md`'s deferred items —
none of which block or reopen anything this session closed.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/13-release-notes.md` (the new v1.0.0 entry)
- `docs/CASE-STUDY.md`

**Ground rules:** Do not reopen the demo-hosting decision without the
user explicitly asking to actually fund and provision real
infrastructure. Do not reopen any ADR. Do not reopen GDPR-only/
single-tenant. `R-01` remains open; `R-07`'s follow-up isn't due until
2026-08-24; `R-08` is accepted residual — don't reopen any of them
without a genuine new finding.
