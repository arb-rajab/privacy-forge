# Case Study: privacy-forge

> An honest account of building a self-hosted GDPR consent/DSAR/retention
> engine over 24 sessions — what was designed, what broke, and how the
> broken parts were found and fixed. Written from the real decision log,
> ADRs, and risk register linked throughout; nothing here is asserted
> without a pointer to the record that backs it.

## What this is

[privacy-forge](../README.md) is a self-hostable consent, data-subject-request
(DSAR), and data-retention engine for small SaaS teams who need a defensible
answer to "prove you handle personal data lawfully" under GDPR/UK-GDPR,
without an enterprise compliance-platform budget. It is a portfolio build,
not a funded product — see the "honest judgment calls" section below for
what that meant in practice.

The two SDLC phases this repository is built to demonstrate *deeply*,
rather than broadly, are Requirements Analysis (full regulatory
traceability from GDPR article → requirement → test) and Retirement,
Handover & Disposal (export, retention, and deletion as the product itself,
not an afterthought) — see `docs/project-memory/00-project-brief.md`.

## The core engineering story: a fail-closed ABAC engine and a tamper-evident audit log

Every sensitive action in this system — verifying a data subject's
identity, approving an erasure, editing a policy, exporting a RoPA
register — passes through one `PolicyEvaluator` service
([ADR-0001](adr/ADR-0001-abac-policy-model.md)). The decision to build a
custom, database-row ABAC engine rather than reach for framework gates or
a third-party policy library was deliberate: separation-of-duties
("the admin who verified identity cannot also approve erasure") needed to
live as *data* — a policy row, auditable and testable like every other
rule — not as a hardcoded `if` buried in a controller that nobody
remembers to test.

Two properties of that evaluator turned out to be load-bearing enough to
need their own decision records, added after ADR-0001 rather than folded
into it silently:

- **Fail-closed by default ([ADR-0006](adr/ADR-0006-policy-evaluator-fail-closed.md)).**
  A missing policy, a malformed condition, an exception during evaluation,
  or a database timeout while fetching policy rows all resolve to `deny`,
  never `allow`. The alternative — fail-open — would have meant a bug or a
  database blip silently grants access to erasure or audit-log viewing,
  which directly contradicts the one thing this product exists to prove.
  Every fail-closed denial is written to the audit log with a
  distinguishing reason code (`policy_missing`, `evaluation_error`) so an
  operator can tell "denied by design" apart from "the evaluator is
  broken." The exhaustive authorisation test suite (NFR-005) includes
  fault-injection cases asserting this, not just the happy-path
  allow/deny matrix.
- **Cross-field comparison in policy conditions ([ADR-0007](adr/ADR-0007-policy-condition-cross-field-comparison.md)).**
  Separation-of-duties needs to compare one attribute of a request (the
  approving actor's ID) against *another attribute of the same request*
  (the DSAR's `identity_verified_by`) — a relationship between two fields,
  not a field against a constant, which the original `in`/`equals`
  condition matcher couldn't express. The two options considered were
  extending the matcher with a general `not_equals_attribute` operator, or
  special-casing the comparison directly in the erasure-approval
  controller. The controller special-case was rejected specifically
  because it would have quietly reversed what ADR-0001 already decided:
  that separation-of-duties belongs in "the same policy registry, the
  same audit trail, and the same exhaustive test suite as every other
  rule, rather than being an exception nobody remembers to test." The
  `dsar.erasure.approve` policy row's `not_equals_attribute:
  resource.identity_verified_by` condition is the result — an ordinary
  policy row, not a special case.

The audit log side of the same story is [ADR-0003](adr/ADR-0003-audit-log-tamper-evidence.md):
a hash chain gives entry-level tamper detection, but a hash chain alone
has a real gap — an attacker with direct database write access can edit
one entry and recompute every subsequent hash, producing a chain that
still verifies as internally consistent. The fix is periodic external
anchoring: a chain's hash is written to object storage *before* any
tampering could happen, outside the attacker's reach even if they own the
database. This is not asserted as working — it's proven by
`tests/Feature/AuditChainAnchorTest.php`, which builds a real 3-entry
chain, anchors it, then simulates a genuinely privileged database attacker
tampering with the first entry and correctly recomputing every downstream
hash. `verifyChain()` alone is fooled (`valid: true`) — the test
demonstrates the exact gap ADR-0003 exists to close. `verifyAnchors()` is
not fooled, because the anchor written before the tampering still holds
the original hash. This is the shape of evidence this whole project tries
to hold itself to: not "the design should prevent X," but "here is a test
that attacks the system the way the threat model says an attacker would,
and here is what actually happens."

## Three real bugs, and what they taught

A feature list is a weaker credibility signal than the bugs a project
found in itself and how it found them. Three are worth detail here,
picked because each represents a different failure class.

### 1. The retention selector re-certified records it had already processed

[`RetentionSelector`](../app/Services/RetentionSelector.php) is the single
query both the dry-run preview and real execution consume
([ADR-0002](adr/ADR-0002-retention-dry-run-parity.md)) — a structural
guarantee that dry-run and real execution can never silently diverge,
because there is only one query, not two hand-synchronised ones. At
Session 12, while investigating a different, adjacent question (whether a
DSAR-driven erasure could leave stale data for retention to re-select —
it couldn't, see below), a real bug surfaced in the same selector: its
`WHERE` clauses checked only the retention-eligibility columns
(`status`/`withdrawn_at` for consent records, `status`/`created_at` for
DSAR requests), but never excluded a row `RetentionExecutor::apply()` had
already acted on. A scheduled run would select the same withdrawn consent
record, "re-anonymise" it, and issue it a second deletion certificate,
forever, on every subsequent run.

The fix reused an existing marker rather than adding a new column:
`ConsentRecord::anonymise()`/`DsarRequest::anonymise()` already prefix a
record's `subject_identifier_hash` with `'anonymised-'` when they act on
it, so the selector now excludes any row already carrying that prefix.
`tests/Feature/RetentionSelectorExclusionTest.php` is written to fail
against the pre-fix selector — a second `retention:execute` run
re-anonymises and re-certifies — and pass against the fix, where a second
run affects zero records. The ADR-0002 parity guarantee is unaffected:
both `preview()` and `execute()` still consume the same query, so the
exclusion applies identically to both.

### 2. The export bundle existed, was tested, and was completely unreachable

Session 8 built connector dispatch, export bundle assembly, and deletion
certificates. `ExportBundle::download_token` and the `dsar.export.download`
named route both existed from that session onward, and the bundle
assembly logic itself was tested. But at Session 10, a plain `grep` before
writing any new code turned up the actual gap: **nothing in the codebase
ever called `URL::temporarySignedRoute('dsar.export.download', ...)`
anywhere.** The only signed link a data subject was ever given was their
*status* link, minted at `DsarController::submit`. The export bundle's
signed-URL machinery — including its 72-hour TTL, a requirement traced
directly to FR-010 — worked exactly as designed in isolation, and was
never reachable by an actual data subject, because nothing on the
data-subject-facing path ever handed them the URL.

This is a different failure class from a broken feature: every individual
piece was correct and covered by a test, and the *integration* — the
literal handoff from "the bundle is ready" to "the person it belongs to
can get to it" — was the part nobody had written or tested. The fix was
to extend `DsarStatusResource` to surface `export_bundles` (with expired
bundles filtered out, so a listed link never immediately 410s) and
`deletion_certificate` through the one link a data subject already holds,
rather than invent a new endpoint. `tests/Feature/DsarStatusTest.php` was
extended to assert the surfaced `download_url` actually resolves (a real
200, not just a present-looking string) — the specific kind of assertion
that would have caught this gap the first time, had it existed then.

### 3. A Docker bind mount silently shadowed `vendor/`, in the same commit that also broke the framework version

The first correction commit after Session 5's initial skeleton
(`97868f1`, "fix(S5): vendor mount, missing config/, DB_PASSWORD
mismatch") fixed a real, mundane infrastructure bug: `docker-compose.yml`'s
`app`/`worker` bind mounts (`.:/var/www/html`) had no exclusion for
`vendor/`, unlike `frontend`'s already-correct handling of `node_modules`
— so a container boot could silently run against whatever (or nothing)
happened to be in the host's `vendor/` directory rather than the
container's own installed dependencies. The fix was an anonymous
`/var/www/html/vendor` volume on both services, the standard pattern for
this exact class of bind-mount-vs-installed-dependencies conflict.

That same commit is also the origin point of this project's most
interesting governance incident (below): its `composer.json` diff quietly
bumped `laravel/framework` from `^11.0` to `^12.61.1`, while the commit's
own `CHANGELOG.md` and handoff entries explicitly argued the opposite —
that the bump should be declined pending verification. Two unrelated
categories of bug (a Docker mount misconfiguration, and a
narrative/diff self-contradiction) shipped in the same commit is itself
a small lesson: a commit that touches infrastructure config and
dependency versions in the same diff makes it easy for a reviewer's
attention to land on the parts that were *discussed* and skip the parts
that weren't.

## The Laravel version governance incident (ADR-0008)

This is the single most differentiated piece of evidence this project
produced, and it deserves to be read in full rather than summarised into
a bullet point.

**The setup.** Session 0's ledger allocation
(`docs/project-memory/00a-ledger-confirmation.md`) froze this repository's
backend as "Laravel 11." Every architecture and requirements document
since named Laravel 11 as the stack. `composer.json` was written that way
at Session 5's initial commit.

**The divergence.** A few hours later, within Session 5 itself, commit
`97868f1` — the same commit described above for its vendor-mount fix —
changed `composer.json`'s `laravel/framework` constraint to `^12.61.1`,
with cascading `pestphp/pest`/`larastan/larastan` bumps. **The commit's
own `CHANGELOG.md` entry, written in the same commit, states the opposite
happened:** that a CVE-driven bump was "not applied... needs human
verification with a checkable source before any version bump." The
narrated decision and the actual file diff directly contradicted each
other, inside one commit, and nobody cross-checked them against each
other before it was made.

**The compounding.** Session 6a (`30dffc1`) was tasked with resolving the
open CVE question with a real source. It did that correctly — fetched the
actual GitHub Security Advisory (`GHSA-5vg9-5847-vvmq`), correctly
determined Laravel 11.x was never in the affected range, and concluded
"no ADR needed... this can be considered closed." That conclusion was
reasoned correctly from the CVE evidence and **wrong about the
repository's actual state**, because it trusted Session 5's narrative
("declined, not applied") instead of opening `composer.json` to check.
Every session from 6a through 19 — fourteen sessions, covering every
migration, every ABAC policy condition, every queued job, every test ever
written in this repository — inherited and repeated the same
trust-the-docs-over-the-file gap. None of them opened `composer.json`
either.

**How it was found.** Session 20 was launched specifically to answer one
question: how did an undecided, unrecorded major-version change end up
running in production-shape code for 14 of 19 sessions? The investigation
was forensic, not a re-read of prior handoffs: it reconstructed the actual
sequence from `git log` and `git show` against the real commits, named
the exact commit hash where the drift was introduced, and quoted the
self-contradicting `CHANGELOG.md` text against the diff sitting three
files away in the same commit — the kind of check ("open the file,
don't trust the narrative about the file") that none of the fourteen
intervening sessions had performed.

**The resolution.** [ADR-0008](adr/ADR-0008-laravel-12-retroactive-adoption.md)
retroactively adopts Laravel 12.x (locked at `v12.66.0`) as the decided
framework version, not by reverting the accidental drift but by formally
deciding it, fourteen sessions late. The reasoning for keeping 12 rather
than reverting to the ledger's original "11" is itself evidence-based, not
convenience: `composer.lock` had pinned Laravel 12 since Session 6a's
first real build, meaning the codebase had *never, at any point after
Session 5's first hour, actually run against Laravel 11* — every one of
165 passing tests at the time of the ADR had only ever been proven against
12. Reverting would trade a known-good, fully green state for a version
the code has literally never executed against, to satisfy a ledger row
rather than fix any actual defect (the CVE that started this never
applied to 11.x in the first place).

**The safeguard.** A `dependency-governance` CI job now diffs
`composer.json`'s `laravel/framework` line against a PR's base branch; if
it changed, the same PR must also touch `docs/adr/` or the decision log,
or the job fails with a message naming this exact failure mode. This was
deliberately scoped narrowly: the root cause here was never an
unreviewed automated bump or a stray `composer update` — it was a commit
whose prose and whose diff disagreed, merged without anyone diffing one
against the other. A CI check that mechanically performs that diff closes
that specific gap, rather than adding broad process weight against a
failure mode that didn't actually occur.

## Honest engineering judgment calls

A project that never says "we stopped here, and here's why" is less
credible than one that does. Two decisions in this project's history are
worth stating plainly rather than glossing over.

### R-08: accepting an unresolved test-infrastructure risk, not chasing it indefinitely

The Pest Browser Testing suite (`tests/Browser/`, real Playwright-driven
Chromium) hung on a Linux sandbox host at Session 16. Rather than
dismiss it as "environment-specific," Session 17 re-tested on a genuinely
different host (Windows 11 + Docker Desktop/WSL2) — and the identical
hang reproduced, disproving the sandbox-specific hypothesis. Session 18
made one further genuine attempt: it read the actual vendor source
(`vendor/pestphp/pest-plugin-browser/src/Playwright/Servers/
PlaywrightNpmServer.php`) rather than re-asserting Session 17's own
theory about *why* it hung, found that theory didn't survive contact with
the code, searched Playwright's own bundled source for an exposed
transport toggle (none exists for a locally-launched browser), and then
tried the single most commonly documented fix for this exact symptom
class (`shm_size: "1gb"` on the Docker service) — confirmed via
process-tree inspection that this did not resolve it either.

At that point, [R-08](project-memory/10-risk-register.md) was formally
accepted as a residual risk at Session 19, rather than carried forward as
an open question indefinitely. The standing mitigation is explicit, not
hand-waved: a curl-based manual walkthrough against the real running
stack proves the backend contract every admin-dashboard button calls
(login → verify-identity → approve-erasure → connector dispatch →
deletion certificate, over real HTTP). What is stated plainly, not
glossed over: **client-side Vue rendering of the admin dashboard remains
genuinely unconfirmed by any automated browser-driven means.** That gap
is accepted as residual, not closed. Three sessions of evidence-based
investigation — a diagnosis, a disproof of that diagnosis by reading
vendor source, and the two most-documented fixes for the symptom both
tried and ruled out — is treated as enough; further time is not spent
chasing it without a new, material change (an upstream fix, or a
minimal non-Pest repro behaving differently).

The demo screenshots in `docs/demo/` (see the [README](../README.md))
were captured by a different path specifically because of this
accepted risk: a small script drives a native, host-installed Chrome
directly over the DevTools Protocol (no Playwright, no Docker-launched
browser, no Pest), which sidesteps the exact mechanism R-08 investigated
rather than re-attempting it.

### The live public demo: explicitly descoped, not silently dropped

Session 1's original decision was that a public hosted demo instance
would exist, deliberately, to maximise reviewer impact — with a hard,
non-negotiable constraint of synthetic data only, isolated infrastructure,
and a spend cap. At Session 24, that decision was revised: real cloud
provisioning was explicitly descoped. The reasoning, recorded in
`09-decision-log.md` rather than left implicit: this is a portfolio
build, not a funded product, and an indefinitely-live public instance
requires ongoing real cloud spend with no revenue behind it — a cost that
was never proportionate to the credibility gain over a rigorously proven
local deployment.

What this did **not** do is quietly relax the underlying safety posture.
The same session built and verified, over genuinely validated HTTPS
against a deliberately fake, RFC 2606-reserved domain
(`demo.privacy-forge.example`) with self-signed (`tls internal`)
certificates, the entire production deployment path: real migrations, a
real login over a real CSRF-protected session, `demo:reset` genuinely
resetting real data (confirmed by creating a record, resetting, and
confirming it was gone — not just "the command exits 0"), and connector
registration confirmed compiled out of the running production image by
inspecting its actual route list. Every Demo Instance Data Safety control
that doesn't itself require real infrastructure was exercised and
confirmed working; only the infrastructure-isolation/spend-cap control is
marked **explicitly not-applicable** — a different, honest thing from
"still missing." A reviewer can read exactly what "demo available" now
means, and exactly why it doesn't mean a live URL, in
`docs/project-memory/08-deployment-and-operations.md` and the Session 24
entries in `09-decision-log.md`.

## Where this stands

As of this session, all nine items in the MVP boundary checklist
(`docs/project-memory/01-scope-and-non-goals.md`) are complete, all four
conditions of that document's own "definition of v1 complete" have been
checked against their real merits rather than asserted, and this document
— along with a refreshed `03-architecture.md` reflecting ADR-0006 through
ADR-0008 by number, and the real demo screenshots in `docs/demo/` — is
the last piece of that checklist. See
`docs/project-memory/12-session-handoff.md` for the session-by-session
account and the explicit taggability verdict.
