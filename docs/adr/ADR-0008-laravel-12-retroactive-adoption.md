# ADR-0008 — Retroactive Adoption of Laravel 12.x (Correcting Undocumented Drift from the Frozen "Laravel 11" Ledger Allocation)

- **Date:** 2026-08-18
- **Status:** accepted (retroactive)

## Context

This repository's Session 0 ledger allocation (`00a-ledger-confirmation.md`)
and every architecture/requirements document since have named "Laravel 11"
as the frozen primary backend. `composer.json` was written that way at
Session 5's initial commit (`d2611fa`): `"laravel/framework": "^11.0"`.

The codebase has not actually been on Laravel 11 since a few hours later,
within Session 5 itself. No session ever deliberately decided to change
it, no ADR was ever opened for it, and `docs/project-memory/09-decision-log.md`
has never mentioned Laravel's version at all — confirmed by reading it in
full as part of this investigation. This ADR exists because Session 20
was launched specifically to find out how an undecided, unrecorded major
version change ended up running in production-shape for 14 of 19
sessions, and to give it a real decision now rather than continuing to
carry it as silent drift.

## What actually happened, reconstructed from git history

**The bump was introduced by commit `97868f1` ("fix(S5): vendor mount,
missing config/, DB_PASSWORD mismatch"),** the very first correction
commit after Session 5's initial skeleton. Its `composer.json` diff:

```diff
-        "laravel/framework": "^11.0",
+        "laravel/framework": "^12.61.1",
...
-        "pestphp/pest": "^2.34",
-        "pestphp/pest-plugin-laravel": "^2.4",
-        "larastan/larastan": "^2.9",
+        "pestphp/pest": "^4.0",
+        "pestphp/pest-plugin-laravel": "^4.0",
+        "larastan/larastan": "^3.9",
```

**This is the central, load-bearing finding: the same commit's own
`CHANGELOG.md` and `docs/project-memory/12-session-handoff.md` entries
state, at length, that this exact bump was considered and *declined*:**

> "A reported CVE (CVE-2026-48019) requiring a Laravel 11→12/13 major
> version bump, plus cascading Pest/Larastan bumps, was **not** applied.
> Could not be verified — no web search tool available, and
> `packagist.org` is unreachable from the sandbox that built this...
> Needs human verification with a checkable source before any version
> bump. See `docs/project-memory/12-session-handoff.md`."

The session handoff written in that identical commit goes further,
explicitly reasoning through *why* it should stay on Laravel 11 pending
verification, and recommends flagging it "explicitly at the start of
whichever session resolves it" if it ever turns out to be real.

**Every word of that reasoning is sound. It simply does not match the
diff sitting three files away in the same commit.** The session that
authored `97868f1` narrated a decision to decline the bump and then
committed the bump anyway — almost certainly because the version
numbers were edited into `composer.json` at some point while evaluating
the claim (to see what it would even look like), the narrative
correctly concluded it shouldn't be kept, and the edit to
`composer.json` was never reverted before the commit was made. This is
not a `composer update` side effect, an automated dependency-bot PR, or
a copy-paste error elsewhere — it is a self-contradiction inside one
human-authored (AI-assisted) commit, where the written record and the
actual file state diverged and nobody cross-checked them against each
other before committing.

**The error then compounded.** Session 6a (`30dffc1`) was tasked with
resolving the open CVE question with a real source. It did exactly
that — fetched the actual GitHub Security Advisory `GHSA-5vg9-5847-vvmq`
and correctly determined Laravel 11.x was never in the affected range
(`<12.60.0` / `<=13.9.0` only), correctly concluded no version bump was
*needed*, and wrote: "No ADR needed. The Session 5 decision to decline
the bump without a verifiable source was the right call... This can be
considered closed; no residual risk tracked forward." **That conclusion
was reasoned correctly from the CVE evidence and wrong about the
repository's actual state**, because it trusted Session 5's narrative
("declined, not applied") instead of opening `composer.json` and
checking. Had it done so, it would have found `^12.61.1` already sitting
there, committed, three commits earlier. Every session from 6a through
19 inherited and repeated this same trust-the-docs-over-the-file gap;
none of them opened `composer.json` to check either.

## Was this ever a deliberate, reasoned decision?

**No.** It is pure accidental drift, fully reconstructed:
- No ADR exists for it (confirmed: `docs/adr/` has none before this one).
- No decision-log entry exists for it (confirmed: `09-decision-log.md`
  never mentions Laravel's version or this CVE at all).
- The one session whose own documentation directly addresses this
  (`97868f1`) explicitly argues *against* the bump — and then ships it.
- The one session that "verified and closed" the question (`30dffc1`)
  did so by validating that the bump wasn't *needed*, and separately,
  silently inherited a bump that had already happened — two different
  facts it treated as one.

## Why this is not being reverted

`composer.lock` has pinned `laravel/framework` to `v12.66.0` since
commit `d0785f2` (Session 6a's first real `docker compose up --build`)
— the codebase has never, at any point after Session 5's first hour,
actually run, been tested, or been developed against Laravel 11. As of
this ADR:

- **165/165 Pest tests pass** against Laravel 12.66.0, covering consent,
  DSAR, ABAC/`PolicyEvaluator`, retention, RoPA, audit-chain, and
  connector functionality built across Sessions 6a–18 — every line of
  it written, run, and only ever proven against Laravel 12.
- **Pint clean, Larastan level 8 clean (0 errors), ESLint clean,
  `docs/architecture/openapi.yaml` validates** — all re-run as part of
  this investigation, against the unmodified current state.
- Every currently-locked dev dependency (`pestphp/pest-plugin-laravel`
  v4.1.0, `larastan/larastan`, `nunomaduro/collision` v8) was itself
  chosen and locked at Session 5's same commit specifically to pair with
  Laravel 12 — reverting `laravel/framework` alone would immediately
  break this entire dependency graph, not just one line.

Reverting now would mean taking a codebase with 14 sessions of
exclusively-Laravel-12 development — every migration, every ABAC policy
condition, every queued job, every test — and running it against a major
version it has *never once* been executed against, for the sole purpose
of matching a ledger row that was itself never re-confirmed after
Session 0. That is a strictly riskier action than the drift it would be
"fixing": it trades a known-good, fully-green state for an untested one,
in service of paperwork, not of any actual defect Laravel 12 has caused.
No functional or security reason to prefer 11 over 12 exists — the CVE
that triggered the original (declined, then accidentally applied) bump
did not even apply to 11.x.

## Decision

**Laravel `^12.61.1` (locked at `v12.66.0`) is retroactively adopted as
this repository's decided framework version, superseding the Session 0
ledger's "Laravel 11" allocation.** The version is not changing as a
result of this ADR — it is only now being formally decided and recorded,
14 sessions after it was accidentally already in effect.

`docs/project-memory/00a-ledger-confirmation.md` is not edited — it is
explicitly a frozen, non-modified governance record of what Session 0
decided at the time, and remains accurate as history. Its ledger row is
superseded by this ADR going forward, not rewritten; readers are pointed
here via an annotation added directly above its frozen content (the same
handling `00a-ledger-confirmation.md`'s own governance rule and Session
19's precedent already establish for this file).

## Consequences

- `02-requirements.md`, `03-architecture.md`, and `README.md`'s "Laravel
  11" mentions are corrected to Laravel 12 to match reality (see this
  ADR's companion decision-log entry for the exact file list).
- `CHANGELOG.md`'s historical entries from Session 5/6a are left
  unedited — they are a dated record of what was written and believed at
  the time (including the "declined" entry, which record a good-faith
  but incorrect belief about the repository's own state) — with a new,
  dated entry added instead, per normal changelog practice of appending
  corrections rather than rewriting history.
- A CI safeguard (see below) now prevents a `laravel/framework`
  constraint change from merging without a corresponding ADR or
  decision-log touch, so this specific failure mode — a narrated
  decision and an actual file diverging, unnoticed for 14 sessions —
  cannot recur silently.

## Safeguard against recurrence

`.github/workflows/ci.yml` gained a new `dependency-governance` job: on
every pull request, it diffs `composer.json` against the PR's base
branch, and if the `laravel/framework` line changed, it requires the
same PR to also touch either `docs/adr/` or
`docs/project-memory/09-decision-log.md`. If neither is touched, the job
fails with an explicit message pointing at this exact failure mode. This
targets the actual root cause found here: not "an unreviewed automated
bump" (there was none) and not "a stray `composer update`" (there was
none of that either) — the root cause was a commit whose prose and whose
diff disagreed, merged without either the author or any downstream
session ever diffing `composer.json` against what the docs claimed. A CI
check that mechanically compares the two closes exactly that gap.

## Revisit triggers

- If Laravel 13 is ever considered, this ADR's reasoning (test suite
  currency, dependency graph coupling) applies again — open a new ADR,
  don't edit this one.
- If the `dependency-governance` CI check produces false positives (e.g.
  a purely cosmetic reformatting of `composer.json` that happens to
  touch the `laravel/framework` line without changing its value), narrow
  its diff check from "line changed" to "constraint value changed"
  rather than disabling it.
