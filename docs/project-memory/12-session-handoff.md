# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 20 — Forensic investigation:
  how and when did Laravel change from 11.x to 12.61.1, and why did no
  session ever report deciding it?**
- Objective: this session's *entire* brief was to resolve one integrity
  question, not to advance feature work. Session 19 had casually
  mentioned "correcting the stale 'Laravel 11' ledger reference to the
  actually-installed 12.61.1" — but no session had ever reported
  *deciding* that change, and it directly contradicted Session 6a's own
  report that a Laravel 11→12 CVE-driven bump was checked against the
  real GitHub Security Advisory and found not to apply, with "no ADR, no
  version bump, nothing to do here." Since a `^11.0` caret constraint
  cannot silently resolve to `12.x`, something concrete must have
  happened to the constraint string itself. Find out exactly what, when,
  by which session, and whether it was ever a deliberate call by anyone —
  then decide, once and for all, whether to revert to Laravel 11 for
  real or retroactively adopt Laravel 12 with honest reasoning, correct
  every stale reference to match, and add a safeguard against this
  recurring silently again.
- Status: **complete.** Root cause fully identified with commit-level
  evidence (see Part A). Verdict: pure accidental drift, not a deliberate
  decision by any session, but with genuine, defensible root-cause
  reasoning available now. Decision: **keep Laravel 12.x**, formally
  adopted via a new retroactive ADR-0008, because 14 sessions of feature
  code, migrations, and all 165 tests have only ever run against it —
  reverting now would be strictly riskier than the drift itself, for no
  functional or security benefit. Every stale "Laravel 11" reference in
  living docs corrected; the frozen Session 0 ledger left unmodified per
  its own governance rule, with a superseding annotation added above it
  instead. A new CI job (`dependency-governance`) added and verified
  (by replaying it against the actual historical commit) to catch this
  exact failure mode in the future. Full verification re-run against the
  unmodified, current codebase: 165/165 tests, Pint, Larastan level 8,
  ESLint, and OpenAPI validation all pass clean. No application code
  changed.

## Part A — Root cause, reconstructed from git history (the actual investigation)

**Step 1 — every commit that touched the `laravel/framework` line in
`composer.json`,** found via `git log -p --follow -- composer.json`:

1. `d2611fa` (Session 5, initial skeleton) — introduces
   `"laravel/framework": "^11.0"`.
2. **`97868f1` (Session 5, "fix(S5): vendor mount, missing config/,
   DB_PASSWORD mismatch")** — changes it to `"^12.61.1"`. **This is the
   one and only commit that ever changed this line.** No commit since
   has touched `laravel/framework`'s constraint again (confirmed: it
   still reads `^12.61.1` in the current `composer.json`).

**Step 2 — read `97868f1`'s full commit message and diff.** The commit
message's stated purpose is three unrelated Docker/config bugs (vendor
mount shadowing, missing `config/` directory, a `.env.example`
password mismatch) — it does not mention a Laravel version change
anywhere in its subject or body. But its `composer.json` diff bumps
`laravel/framework` (`^11.0` → `^12.61.1`), `pestphp/pest` (`^2.34` →
`^4.0`), `pestphp/pest-plugin-laravel` (`^2.4` → `^4.0`), and
`larastan/larastan` (`^2.9` → `^3.9`) — a coordinated, deliberate-looking
set of edits, not a stray accidental keystroke.

**The critical discovery: this same commit's `CHANGELOG.md` and
`docs/project-memory/12-session-handoff.md` entries explicitly discuss
this exact bump — and say it was declined:**

> "A reported CVE (CVE-2026-48019) requiring a Laravel 11→12/13 major
> version bump, plus cascading Pest/Larastan bumps, was **not** applied.
> Could not be verified — no web search tool available, and
> `packagist.org` is unreachable from the sandbox that built this
> (confirmed by testing, not assumed). Needs human verification with a
> checkable source before any version bump."

The handoff entry goes further, reasoning carefully about why declining
without a verifiable source was the right call, and explicitly
recommends that whichever session resolves it "flag this explicitly at
the start" if the bump ever turns out to be real. **This is good
reasoning, entirely undermined by the fact that the very commit
containing it also ships the bump it describes as rejected.** The
`composer.json` edit and the prose describing a decision not to make
that edit were both written in the same commit, and nobody — not the
session that wrote it, not any of the 13 sessions since — ever diffed
the two against each other.

**Step 3 — checked every session's summary and `docs/project-memory/
09-decision-log.md`/`docs/adr/` for any mention, as instructed, rather
than assuming there was none.** Confirmed: `09-decision-log.md` has
never mentioned Laravel's version or this CVE, in any of its Session 0
through Session 18 entries (grepped in full). No ADR before this session
mentions it either (`docs/adr/` had exactly 7 files, ADR-0001 through
ADR-0007, none about dependencies). The only other places this CVE is
mentioned at all are Session 6a's commit (`30dffc1`) and Session 6b's
commit (`7c909f8`, a passing reference to 6a's resolution) —
`git log -S "CVE-2026-48019"` confirms these are the only three commits
that ever mention it.

**Session 6a (`30dffc1`) is where the error compounds.** Tasked with
resolving the open CVE question with a real source, it did the
investigation correctly: fetched the actual GitHub Security Advisory
`GHSA-5vg9-5847-vvmq`, correctly found Laravel `11.x` was never in the
affected range (`<12.60.0` / `<=13.9.0` only), and concluded: "No ADR
needed. The Session 5 decision to decline the bump without a verifiable
source was the right call... This can be considered closed; no residual
risk tracked forward." **The CVE analysis itself was correct. The
conclusion about the repository's state was not** — Session 6a trusted
Session 5's narrative ("declined, not applied") instead of opening
`composer.json`, which by then had already carried `^12.61.1` for one
commit. Every session from 6a through 19 repeated the same gap: none of
them ever diffed the actual file against what the documentation claimed.

**Step 4 — checked whether the actually-running, actually-tested
application has been on Laravel 12.x for real, or whether this is very
recent, unexercised drift.** It is not recent: `composer.lock` was first
committed in `d0785f2` ("fix(S5→S6): real docker boot verification, lock
files, CI/lint bugs" — Session 6a's environment-verification work),
already locking `laravel/framework` to `v12.61.x`-and-up; the currently
checked-out `composer.lock` resolves it to **`v12.66.0`**, confirmed
directly against the live `privacy-forge-app-1` container
(`php artisan --version` → `Laravel Framework 12.66.0`). Every feature
built from Session 6a onward — consent capture, DSAR intake, the ABAC
`PolicyEvaluator`, retention/RoPA, audit-chain tamper evidence, connector
dispatch, the admin dashboard, the embeddable widget — was written,
migrated, and tested exclusively against this version. **This session
re-ran the full verification suite against the untouched, current
codebase to confirm this empirically, not just from old handoff
claims:** `composer test` → **165/165 passed** (664 assertions, 119.56s);
`composer lint` (Pint) → clean, 152 files; `composer analyse` (Larastan
level 8) → **0 errors**; `npm run lint` (ESLint) → clean;
`docs/architecture/openapi.yaml` → validated with the actual
`openapi-spec-validator` tool CI uses (via a throwaway `python:3.12-slim`
container, matching how CI itself validates it) → **valid**.

**Step 5 — was this ever a deliberate, reasoned choice by any session?**
No. It is pure accidental drift with a fully reconstructed mechanism: a
session that investigated a version bump, wrote a well-reasoned decision
*against* applying it, and shipped the bump anyway in the same commit,
followed by 13 sessions that never cross-checked the file against the
docs. There is no point at which any session looked at `composer.json`,
saw `^12.61.1`, and made an active choice to keep, revert, or document
it — until this one.

## Part B — The decision: keep Laravel 12.x, documented retroactively

Per this session's own remediation instructions: revert only if this
was pure drift with no evidence Laravel 12.x provides anything the
project needs; otherwise write a real, honest retroactive ADR. The
evidence gathered in Part A settles this in favor of **keeping Laravel
12.x**, not because Laravel 12 provides some 12-only API this codebase
exploits (a check for this found none — Laravel 11→12 was itself a
light upgrade with few breaking changes), but because **14 sessions of
real, passing, re-verified-this-session functionality have never once
run on anything else.** Reverting now would mean:
- Downgrading `laravel/framework` to a version the entire test suite,
  every migration, and every ABAC policy condition has never executed
  against, as a one-shot, high-risk experiment this late in the project.
- Cascading downgrades to `pestphp/pest-plugin-laravel`,
  `larastan/larastan`, and `nunomaduro/collision`, all of which were
  locked at the *same* Session 5 commit specifically paired with
  Laravel 12 — reverting one line, not the whole graph, was never a real
  option.
- Trading a fully-green state (verified fresh this session) for an
  unknown one, in service of matching a ledger row that itself was never
  re-confirmed after Session 0 — with zero corresponding functional or
  security upside, since the triggering CVE never applied to Laravel
  11.x either way.

**Written up as `docs/adr/ADR-0008-laravel-12-retroactive-adoption.md`,**
with the full mechanism above, an honest statement that this was adopted
through undocumented drift rather than deliberate choice, and the actual
justification for keeping it now. Indexed in
`docs/project-memory/09-decision-log.md`.

## Part C — Corrections and safeguard

**Stale "Laravel 11" references corrected** in the living docs that
should reflect current reality:
- `docs/project-memory/02-requirements.md` ("Stack is fixed" line).
- `docs/project-memory/03-architecture.md` (C4 container diagram).
- `README.md` (stack summary line).

**`docs/project-memory/00a-ledger-confirmation.md` was *not* edited.**
That file's own closing governance note states "This file is not
modified again" — it is Session 0's frozen record of what was allocated
at the time, and remains historically accurate as such (Session 19
already established this same precedent by putting its correction in
`14-maintenance-and-retirement.md` rather than editing the ledger). A
short annotation was added *above* the frozen content (not touching the
frozen table itself) pointing to ADR-0008, so a reader of the ledger
sees the correction without the historical record being rewritten.

**`CHANGELOG.md`'s Session 5/6a historical entries were left
unedited** — they are a dated record of a good-faith belief at the time
(including the "declined" entry, which was true of the session's
*intent*, if not of the file it actually committed) — and a new, dated
`[Unreleased]` entry was added instead, per normal changelog practice of
appending corrections rather than rewriting history.

**Safeguard added:** `.github/workflows/ci.yml` gained a new
`dependency-governance` job — on every pull request, it diffs
`composer.json` against the PR's base branch, and if the
`laravel/framework` constraint line changed, requires the same PR to
also touch `docs/adr/` or `docs/project-memory/09-decision-log.md`, or
the job fails with an explicit pointer to this exact failure mode.
**Verified, not assumed:** replayed the check's exact logic
(`git diff <base>...<head>` + the same grep patterns) against the real
historical commits `d2611fa`→`97868f1` — confirmed it detects the
`laravel/framework` change and confirms neither `docs/adr/` nor the
decision log was touched in that diff, i.e. **this exact check would
have caught the original incident** had it existed at the time. The new
job's YAML was parse-validated (`python -c "import yaml; yaml.safe_load(...)"`)
before being treated as done.

## What was explicitly NOT done this session, and why

1. **No application code, dependency versions, migrations, or
   `composer.lock` changed.** This was a documentation/governance
   correction, not a dependency change — the version was never actually
   in question, only whether it should be kept and recorded.
2. **No other ADR reopened.** ADR-0001 through ADR-0007 are untouched.
3. **`README.md`'s broader staleness (its "Session 5"/"Session 13"
   status lines predate this session by many sessions) was not
   addressed** — out of scope for this session's single-issue brief;
   only its "Laravel 11" stack line was corrected.
4. **No attempt to determine why Session 5's narrative and diff diverged
   beyond "the edit was never reverted before committing"** — the AI
   sandbox that authored that commit is not available to interrogate
   further; the git evidence is conclusive enough to establish the
   mechanism without speculating about exact keystrokes.

## Files created or changed

`docs/adr/ADR-0008-laravel-12-retroactive-adoption.md` (new),
`docs/project-memory/09-decision-log.md` (ADR-0008 entry),
`docs/project-memory/00a-ledger-confirmation.md` (superseding annotation
only, frozen content untouched), `docs/project-memory/02-requirements.md`,
`docs/project-memory/03-architecture.md`, `README.md` (stale "Laravel 11"
references corrected), `CHANGELOG.md` (new dated entry, history
unedited), `.github/workflows/ci.yml` (new `dependency-governance` job),
this file. **No application code, dependencies, or `composer.lock`
changed.**

## Validation performed

- **`composer test` (Pest, inside `privacy-forge-app-1`) → 165/165
  passed, 664 assertions, 119.56s** — against the unmodified current
  codebase, re-run fresh this session (not assumed from a prior
  session's claim).
- **`composer lint` (Pint) → clean, 152 files.**
- **`composer analyse` (Larastan, level 8) → 0 errors.**
- **`npm run lint` (ESLint, inside `privacy-forge-frontend-1`) →
  clean.**
- **`docs/architecture/openapi.yaml` → valid**, checked with the actual
  `openapi-spec-validator` tool via a throwaway `python:3.12-slim`
  container, matching CI's own method.
- **`.github/workflows/ci.yml` → valid YAML** (parsed with PyYAML in a
  throwaway container) after adding the `dependency-governance` job.
- **The new CI check's detection logic was replayed against the real
  historical `d2611fa`→`97868f1` diff and confirmed to correctly flag
  it** — the closest available proxy for "does this safeguard actually
  work," short of opening a live PR.
- `docker ps` confirmed the full 6-service stack (`app`, `worker`,
  `frontend`, `postgres`, `redis`, `minio`) was healthy throughout this
  session's investigation (Docker Desktop had to be started fresh at the
  beginning of this session; containers came up automatically once the
  daemon was ready, matching Session 19's note that they were already
  running before that session started).

## Open questions and risks

- **This session's own subject — closed.** The Laravel version question
  (this session's entire brief) is resolved: ADR-0008 accepted, docs
  corrected, safeguard added and verified, full green build confirmed.
  Nothing about it remains open.
- **R-01 — unchanged, still open.**
- **R-02, R-04, R-05, R-06 — unchanged, still closed.**
- **R-07 — still closed (Session 18), rate-limit follow-up re-checked in
  Session 19 and still blocked at that time, unchanged this session.**
  `HTTP/2 429` persisted from `codeload.github.com` as of Session 19. A
  dated, actionable trigger is in the risk register (re-check the same
  `curl` command; if still blocked on or after 2026-08-24, treat it as no
  longer transient) — not re-checked again this session, since this
  session's brief was the Laravel version question only. The composed
  ~716s estimate remains the standing number; not urgent given the margin
  under the 900s budget.
- **R-08 — formally accepted as a residual risk since Session 19, still
  "Accepted (residual risk)," unchanged this session.** See
  `10-risk-register.md`'s "Accepted residual risks" section. Reopen only
  on a materially new input (an upstream Playwright/`pest-plugin-browser`
  fix, or a report on the untried minimal-repro lead) — not simply more
  investigation time on the same three sessions' worth of evidence.
- **B-01/B-02/B-03 (Session 19), unchanged this session** — full-instance
  archival export gap, `RetentionPolicyController::store`
  duplicate-policy validation gap, and CI's missing scheduled re-scan
  trigger — all tracked in `11-backlog.md`, all still open.
- **Optional/stretch items (retention UI, RoPA export button, policy
  management UI, audit log view) — all still open, all API/CLI-only**,
  unchanged from prior sessions.

## Next recommended session

This session was single-issue by design (the Laravel version integrity
question) and deliberately touched no feature work, so the prior
session's queue is entirely unchanged and still the right place to pick
up:

1. **`B-01` — the full-instance archival export**, if a session wants to
   pick up genuinely new feature work: reuse
   `ExportBundleAssembler`/`RopaController`'s existing JSON/CSV
   conventions across every first-class model (see
   `14-maintenance-and-retirement.md`, "Archival export format," for the
   proposed shape).
2. **R-01** — audit-log DB-grant revocation, the one remaining genuinely
   open risk (needs a second, non-owning Postgres role).
3. **A quick confirmation re-measurement of R-07's exact final
   Dockerfile**, once the rate limit clears (re-check via the dated
   trigger in `10-risk-register.md` — try again on or after 2026-08-24 if
   still blocked at that point). Low priority given the margin under
   budget either way.

- Inputs required: this file, `docs/adr/ADR-0008-laravel-12-retroactive-adoption.md`,
  `docs/project-memory/09-decision-log.md`, `docs/project-memory/10-risk-register.md`
  (R-01/R-07/R-08), `docs/project-memory/11-backlog.md` (B-01/B-02/B-03).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 20
complete.

**Current stack:** unchanged (no dependency versions touched this
session) — Laravel `^12.61.1` (locked at `v12.66.0`), now **formally,
retroactively decided via ADR-0008** rather than undocumented drift — see
this file's Part A/B for the full forensic account. PHP 8.3, Vue
3/Inertia, PostgreSQL 16, Redis 7, S3-compatible storage,
`barryvdh/laravel-dompdf`, `pestphp/pest-plugin-browser`. No new
dependencies this session — a documentation/CI-governance-only session.

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0-19 remain in force, plus the new **ADR-0008 (Laravel 12.x,
retroactive)** — do not revert `laravel/framework` to `^11.0` without
reopening ADR-0008 first and re-running the full test suite against
Laravel 11, since it has never once been executed against that version.

**Implementation state:**
- Done: everything from Session 19, plus: the Laravel version drift
  fully traced to commit `97868f1` and its self-contradicting
  narrative-vs-diff; ADR-0008 written and indexed in the decision log;
  stale "Laravel 11" references corrected in `02-requirements.md`,
  `03-architecture.md`, and `README.md`; a superseding annotation added
  above (not into) the frozen `00a-ledger-confirmation.md`; a new
  `[Unreleased]` CHANGELOG entry; a new `dependency-governance` CI job
  that fails any PR changing the `laravel/framework` constraint without
  also touching `docs/adr/` or the decision log (verified against the
  real historical commit that caused this incident).
- In progress: nothing mid-flight.
- **Known gaps to check first (unchanged from Session 19):** (1) R-01 —
  DB-level grant revocation for the audit log unbuilt; (2) R-07's
  rate-limit follow-up — re-check the dated trigger in
  `10-risk-register.md` (2026-08-24 if still blocked before then); (3)
  R-08 — accepted as residual, the minimal-repro lead remains available
  but not urgent; (4) `B-01` — no full-instance archival export exists
  yet, only per-subject (US-008) and RoPA (US-013) exports; (5) `B-02` —
  `RetentionPolicyController::store`'s duplicate-active-policy gap; (6)
  `B-03` — CI's `osv-scanner`/CodeQL/gitleaks jobs have no scheduled
  re-run trigger; (7) no password reset flow; (8) retention/RoPA/policy/
  audit-log management UIs remain API/CLI-only.
- Not started: unchanged from Session 19 (a registry-hosted prebuilt
  image for R-07, a fix for R-08's underlying hang, connector secret
  rotation, HTTP connector-management, email/notification delivery,
  password reset, the three backlog items, the public demo instance's
  isolated infrastructure/spend cap/scheduled reset).

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — this session introduced no new
architectural pattern or dependency.

**Task for next session (single objective):** no single forced next
step — this session's own subject is fully closed. Reasonable options,
roughly in order of value: pick up a `11-backlog.md` item (B-01
full-instance export is the most substantial), or R-01 (audit log
DB-grant revocation, the one remaining genuinely open risk).

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/adr/ADR-0008-laravel-12-retroactive-adoption.md`
- `docs/project-memory/09-decision-log.md`
- `docs/project-memory/10-risk-register.md` (R-01/R-07/R-08)
- `docs/project-memory/11-backlog.md` (B-01/B-02/B-03)

**Ground rules:** Do not change the stack. Do not reopen ADR-0001 through
ADR-0007. **Do not revert or bump `laravel/framework` without opening a
new ADR and re-running the full test suite** — ADR-0008 explains why.
R-01 remains open — do not fold a fix in silently. R-02/R-04/R-05/R-06/
R-07 are closed, and R-08 is accepted as a residual risk — do not reopen
any of them without a genuine new finding. The new `dependency-governance`
CI job will fail any PR that changes `laravel/framework` without also
touching `docs/adr/` or the decision log — this is intentional, not a
bug to route around.
