# Maintenance and Retirement
> Purpose: the whole life of the system, including its end
> Project: privacy-forge (public)
> Last updated: 2026-08-17 (Session 19)
> Depth: **DEEP** — this is one of the repository's two demonstrated-deeply SDLC phases (the other is `02-requirements.md`; see `docs/project-memory/00a-ledger-confirmation.md`, Session 0, which named this phase deliberately because it is "the phase almost no portfolio demonstrates; here it is the product itself").

## How to read this document

Three different kinds of content are deliberately mixed below, and each
claim below is labelled so a reader doesn't have to guess which is which:

- **Cited** — evidence that already exists in this repository's code or
  tests, written at an earlier session for its own product reasons. This
  document points at it; it does not re-describe a new mechanism.
- **New, written this session** — genuinely new content this repository
  had nowhere else: a decommissioning runbook and an archival format
  assessment. Concrete enough to actually follow, not aspirational.
- **Flagged gap** — something this session found does *not* exist, stated
  plainly rather than silently built (a large feature) or silently
  ignored (a real limitation).

## Maintenance cadence

- **Dependency updates — flagged gap.** No Dependabot or Renovate
  configuration exists (`.github/` contains only `workflows/ci.yml`,
  `ISSUE_TEMPLATE/`, `PULL_REQUEST_TEMPLATE.md` — no `dependabot.yml`).
  Dependency versions are bumped manually, by whichever session's work
  happens to touch `composer.json`/`package.json`. There is no standing
  process that opens a PR when a dependency has a new release.
- **Vulnerability scanning — cited, but scoped to pushes/PRs only.**
  `.github/workflows/ci.yml` runs `gitleaks` (secret scanning),
  `osv-scanner` (known-vulnerability scanning against
  `composer.lock`/`package-lock.json`), and CodeQL — but all three trigger
  only `on: push`/`pull_request` to `main` (`ci.yml` lines 3-7). There is
  no `schedule:` trigger, so a CVE published against a dependency already
  merged into `main` — with no further pushes — is never re-caught by
  this pipeline on its own. **Recommendation, not built this session:** a
  weekly `schedule:`-triggered run of the existing `osv-scanner` job would
  close this cheaply (no new tool, just a new trigger) — flagged here for
  a future session rather than added speculatively under this one's
  retirement/handover banner.
- **Security patching SLA — cited from `SECURITY.md`, not invented here.**
  `SECURITY.md`'s disclosure process commits to "acknowledgement within 5
  business days" of a reported vulnerability, followed by assessment,
  private-branch fix development, and coordinated disclosure. There is no
  broader SLA (e.g. a committed patch-within-N-days figure) beyond that
  acknowledgement commitment — appropriate for a self-hosted, no-support-
  contract open-source project (see "Support model" below), not
  understated for a product that actually promises more.
- **Backup restore test — flagged gap, cross-referenced.**
  `03-architecture.md`'s "Backup and recovery design" section states
  target RPO ≤ 24 hours / RTO ≤ 4 hours, explicitly noting these are
  "stated targets, to be verified by an actual restore drill" — and names
  Session 8 as where that drill and `08-deployment-and-operations.md`'s
  "Backup and restore" section (currently still "last verified: NEVER")
  would be completed. That drill has still not happened. This is Phase 7
  (Operations & Maintenance) territory, which `docs/SDLC-EVIDENCE.md`
  already marks **intentionally light** for this repository (operational
  depth is deliberately demonstrated in `pulsewatch`/R03 instead, not
  duplicated here) — so this gap is being *named*, not fixed, under this
  session's Phase 8 scope. It matters here only because the
  decommissioning runbook below depends on the underlying data still
  being intact and restorable right up to the moment an instance is
  retired; an unverified backup is a real risk to that, stated honestly
  rather than assumed away.

## Support model and expectations

This is a self-hosted, public flagship portfolio project — not a
commercially supported product, and this section says so plainly rather
than inventing a support tier that doesn't exist:

- **No SLA, no support contract.** `SECURITY.md` (Session 4/5) is explicit
  that "no versioned release exists yet"; its 5-business-day
  acknowledgement commitment covers *reported vulnerabilities* specifically,
  not general support requests.
- **Licence sets the expectation deliberately.** `README.md`'s licence
  section chose AGPL-3.0 "because this is a hostable application, not a
  library; AGPL ensures modifications to a hosted version remain
  shareable" — i.e. self-hosters are expected to operate and maintain
  their own instance, with the freedom (and responsibility) to modify it
  themselves.
- **Support channel, as it actually exists today:** `CONTRIBUTING.md`
  asks that a GitHub issue be opened before a large PR; general triage and
  bug reports would follow the same GitHub issue tracker. There is no
  separate helpdesk, ticketing system, or paid tier.
- **What this means for someone evaluating self-hosting it:** treat this
  the way any unsupported open-source self-hosted tool should be treated
  — evaluate the code and tests directly (this document's own citations
  are one way to do that), don't assume a vendor is standing behind it.

## Data export and portability

**Cited, not reinvented.** The mechanism a data subject uses to get their
own data back out of this system already exists and is exercised by real
tests — this section points at it rather than describing a new one:

- **US-008** (`docs/project-memory/02-requirements.md`) is the requirement
  ("As a data subject, I want to receive my data as a downloadable
  bundle... in JSON and CSV, encrypted at rest, and made available via a
  signed URL with a TTL of no more than 72 hours").
- **`App\Services\ExportBundleAssembler::assemble()`** builds both formats
  (`toJson()`/`toCsv()`) once every connector export task for a DSAR has
  succeeded, encrypts the contents with `Crypt::encryptString()` before
  they ever reach object storage (application-layer encryption,
  deliberately not relying on bucket-level SSE — see the class's own
  comment), and creates one `ExportBundle` row per format with a
  `signed_url_expires_at` set from `connectors.export_bundle_ttl_hours`.
- **`App\Http\Controllers\ExportBundleController`** enforces the 72-hour
  TTL *twice*, independently — Laravel's own request-signature expiry at
  link-generation time, and a second check against the row's own
  `signed_url_expires_at` — "so a bug or a since-revoked link can't
  outlive the row's own recorded expiry" (the controller's own comment).
  An expired or invalid link returns a `410` with an RFC 7807 problem
  body, never a silently-extended link.
- Built at Session 8 (per this file's own change history embedded in the
  code's Session references) and exercised in Session 10's parity/status
  work; proven working end-to-end in a real container-to-container walk
  (Session 16's manual walkthrough, `12-session-handoff.md`).
- **Scope, stated precisely:** this is *per-DSAR-subject* export
  portability (US-008's actual requirement — "receive **my** data"), not
  a full-instance export. See "Archival export format" under
  Decommissioning below for why that's a materially different mechanism,
  and why it does not yet exist.

## Data retention and deletion schedule

**Cited.** The retention engine and its dry-run/execution parity guarantee
are real, implemented, and covered by a dedicated parity test — this
section connects to that evidence rather than describing a new policy
engine:

- **US-010/011/012** (`02-requirements.md`) are the requirements: define a
  retention period + post-expiry action per data category (US-010),
  preview what a policy would affect with no side effects before it runs
  for real (US-011), and run it automatically on a schedule, producing a
  stored certificate (US-012).
- **ADR-0002 — Retention Dry-Run / Execution Parity** (`docs/adr/
  ADR-0002-retention-dry-run-parity.md`) is the design decision this
  schedule structurally relies on: a single `RetentionSelector` service
  is the *only* place selection logic lives; `RetentionExecutor` consumes
  that same selector's query in both `preview()` (dry run, no side
  effects — `App\Services\RetentionExecutor::preview()`) and `execute()`
  (real run — `RetentionExecutor::execute()`), so dry-run and real-run
  "cannot diverge, because there is only one selection code path" (the
  ADR's own Decision section). `tests/Feature/
  RetentionDryRunParityTest.php` asserts this directly — dry-run
  candidate IDs and a subsequent real run's affected IDs are identical
  given unchanged data.
- **Scheduling:** `routes/console.php` registers
  `Schedule::command(ExecuteRetentionPoliciesCommand::class)->daily()` —
  "every active retention policy is re-evaluated once daily" (the
  route file's own comment, US-012). This is deliberately *not* gated by
  `PolicyEvaluator`/ABAC — a Session 11 decision-log entry ("Retention
  execution: scheduler boundary, not a new ABAC action") records why: the
  scheduled real run is a system-initiated process, not a staff HTTP
  request, so it sits on the scheduler/worker side of the authorisation
  boundary rather than being a new sensitive action a staff member
  triggers.
- **Post-expiry actions:** `RetentionExecutor::apply()` supports
  `anonymise` (calls the record's own `anonymise()` method) and `erase`
  (`ConsentRecord::retentionErase()` — a deliberate, documented bypass of
  the same model's normal delete-guard that protects consent withdrawal
  from ever being a hard delete; `DsarRequest::delete()` for that model,
  which has no such guard).
- **Known, pre-existing limitation, carried forward honestly rather than
  silently repeated as new information:** `RetentionPolicyController::
  store` does not prevent two independently-created `active` policies for
  the same data category (only the `update` path's supersede-then-create
  guarantees uniqueness) — a Session 11 gap, restated at Session 12, still
  open. It was previously tracked only in the decision log, not in
  `11-backlog.md` (which was itself still an empty template until this
  session added it as B-02) — not part of this session's scope to fix,
  but now actually tracked where the project's own convention says it
  should be.

## Deletion certificates

**Cited.** A deletion certificate is the evidentiary artifact US-009 and
US-012 both require, and it already has a real, constraint-enforced
"exactly one source" design — this section points at that, not a new one:

- **US-009** (Session 8): "As a data subject, I want confirmation that my
  data was actually erased... the certificate explicitly states the
  exception rather than claiming full erasure — the system must never
  overstate what it achieved."
- **`App\Services\DeletionCertificateGenerator::generate()`** builds the
  DSAR-side certificate: it separates connector tasks into `confirmed`
  (status `success`) and `exceptions` (`failed`/`partial`), and — per the
  class's own header comment quoting FR-011 — is "generated whenever
  every erasure task for a DSAR reaches a terminal state — success,
  failed, or partial alike... so an incomplete erasure still produces
  evidence, just honest evidence, rather than no certificate at all."
- **`App\Services\RetentionExecutor::execute()`** builds the
  retention-side certificate for a real (non-dry-run) execution, summarised
  via `summarise()` (record count, action taken, data category, retention
  period).
- **The "exactly one source" constraint (Session 11):** `App\Models\
  DeletionCertificate` is a single shared table for both origins
  (`dsar_request_id` for US-009, `retention_execution_id` for US-012) —
  this was the ERD's design since Session 3, not a new redesign — but
  Session 11 added a DB **CHECK constraint**
  (`deletion_certificates_exactly_one_source`, see the migration
  `database/migrations/2026_08_16_000004_add_retention_execution_foreign_
  to_deletion_certificates_table.php`) requiring exactly one of the two
  foreign keys to be set, never both, never neither — "so the two sources
  are structurally distinguishable... without a separate 'source' column
  that could drift" (the migration's own comment). This is enforced at
  the database level, not merely by application convention — the same
  discipline this project applies elsewhere (e.g. the audit log's
  append-only DB grants, ADR-0003).
- Covered by `tests/Feature/DeletionCertificateTest.php` and
  `tests/Feature/RetentionExecutionTest.php`.

## Handover pack

What an operator retiring this instance actually walks away with, so the
organisation retains real evidence of its own compliance history rather
than losing it the moment the instance stops running:

1. **A final RoPA export**, covering every active processing purpose with
   its lawful basis, retention period, and data categories/subjects — the
   existing, real mechanism: `GET /admin/policies`-equivalent RoPA export
   at `App\Http\Controllers\Admin\RopaController::export()` (`?format=csv`
   or `?format=pdf`), gated by the `ropa.export` ABAC policy (US-013,
   Art. 30 RTM row). This is Owner/Privacy-Manager-only and already
   produces a durable, external-sharing-suitable artifact — no new code
   needed; see step 1 of the decommissioning runbook below for exactly
   how to run it.
2. **A final audit-log chain verification result.** `php artisan
   audit:verify-chain` (`App\Console\Commands\VerifyAuditChainCommand`,
   R-04/ADR-0003) already exists and checks both layers — the entry-level
   hash chain (`AuditLogger::verifyChain()`) and the externally anchored
   sequences (`AuditLogger::verifyAnchors()`, closed at Session 17). Its
   output (valid/invalid, and the anchor count checked) is the evidence
   that the audit trail was intact at the moment of retirement — see step
   2 of the runbook.
3. **Whatever DSAR export bundles are still live.** Per-subject export
   bundles (US-008, above) already carry their own 72-hour TTL by design
   — they are not meant to be a long-term handover artifact, and
   `03-architecture.md`'s backup design deliberately excludes them from
   long-retention backups for the same reason (retaining an expired
   export would quietly contradict the TTL promise). The handover pack
   does not try to extend their life; it relies on the RoPA export and
   audit-chain verification instead for the organisation's durable record.
4. **This document, and the rest of `docs/project-memory/`**, as the
   design/decision record behind everything above.
5. **A full-instance data archive — flagged gap, not built this
   session.** See "Archival export format" under Decommissioning below:
   no mechanism exists today that dumps *all* of an organisation's
   consent records, DSAR history, retention executions, and deletion
   certificates into one self-describing, application-independent
   artifact. The runbook below gives the operator a real, working
   substitute available today (`pg_dump` + an object-storage sync), and
   this gap is proposed as a concrete future-session item rather than
   quietly built as a large new feature under this session's scope, or
   quietly ignored.

## Decommissioning procedure

**New content, written this session.** Because ADR-0005 fixed this
repository as single-organisation with no tenant column, "tenant
offboarding" here means exactly one thing: *this specific self-hosted
instance is being retired.* There is no other organisation sharing it to
migrate off first. This is a real, concrete runbook — every command below
is one that exists and runs against the actual `docker-compose.yml`
services in this repository today (`postgres:16-alpine`, `minio/
minio:latest`, `app`), not a hypothetical.

### Step 0 — Decide and communicate

Confirm the decision to retire with whoever inside the organisation relies
on this instance for GDPR/UK-GDPR compliance evidence (Privacy Manager,
Owner). Set a retirement date. Everything below should happen on or
before that date, while the instance is still fully operational — a
decommissioning runbook that only works on a *healthy* instance is the
only kind worth writing; if the instance is already broken, restore from
backup first (see "Backup restore test" above — and if that drill has
never actually been performed, this is exactly the moment that gap stops
being theoretical).

### Step 1 — Final RoPA export, for the organisation's own records

As an Owner or Privacy Manager, authenticated via the real admin login
(Session 14):

```
curl -s -b cookies.txt "https://<your-instance>/admin/ropa/export?format=csv" \
  -o ropa-final-export.csv
curl -s -b cookies.txt "https://<your-instance>/admin/ropa/export?format=pdf" \
  -o ropa-final-export.pdf
```

Keep both. The CSV is the machine-readable record (Art. 30 evidence); the
PDF is the human-readable one an auditor or DPO would actually read.

### Step 2 — Final audit-log chain verification, before the anchor destination becomes unreachable

Run this **while the instance's `s3`/MinIO disk (or whatever object
storage the anchors were written to — R-04/ADR-0003) is still reachable**
— `verifyAnchors()` needs to read the anchored hashes back from external
storage to compare them against the live chain, and that comparison is
only meaningful while both sides still exist:

```
docker compose exec app php artisan audit:verify-chain
```

Save the full output (exit code, and the "Anchors valid (N checked)" or
failure detail). This is the evidence that the audit trail was not
tampered with up to the moment of retirement — the entire reason ADR-0003
and R-04 exist. If this command reports a broken chain or a mismatched
anchor, **stop and investigate before proceeding** — decommissioning a
tampered instance without recording that fact defeats the purpose of
having built tamper-evidence at all.

### Step 3 — Data export options for the organisation's own records

Two real options exist today, in increasing order of completeness. Use
both if retention obligations or organisational policy call for it; the
second is what actually satisfies "we kept a complete copy" today, given
the gap named below.

**3a. Per-subject exports (US-008, if any DSARs are still pending or
recently completed).** Any live `ExportBundle` records can still be
downloaded via their existing signed URLs (subject to their own 72-hour
TTL — see "Handover pack" above for why these are not extended for this
purpose).

**3b. A full PostgreSQL logical dump — the real, available-today
substitute for a full-instance export:**

```
docker compose exec postgres pg_dump -U privacy_forge --format=plain \
  privacy_forge > privacy-forge-final-dump-$(date +%Y-%m-%d).sql
```

Use `--format=plain` deliberately, not the default custom/binary format —
plain SQL text remains readable (and greppable) with nothing more than a
text editor, years after this application itself is gone, whereas a
custom-format dump requires `pg_restore` from a version-compatible
`pg_dump` to read at all. See "Archival export format" immediately below
for why this is a substitute, not the final answer.

**3c. Object storage contents (export-bundle ciphertext, RoPA artifacts
if stored there, audit anchors):**

```
docker run --rm -v "$(pwd)/minio-final-export:/export" \
  --network privacy-forge_default minio/mc \
  mirror http://minio:9000/<bucket> /export
```

(Substitute real MinIO/S3 credentials and bucket name from the instance's
own `.env`; use the equivalent `aws s3 sync` command if the deployment
uses real S3 rather than MinIO.)

### Step 4 — Secure disposal

Once steps 1-3 are complete and their outputs are safely stored outside
this instance:

```
docker compose down
docker volume rm privacy-forge_postgres-data privacy-forge_minio-data
docker rmi privacy-forge-app:latest privacy-forge-app-e2e:latest
```

(Volume names are Compose's default `<project>_<volume>` pattern for the
two named volumes declared in `docker-compose.yml` — `postgres-data`,
`minio-data`; confirm the actual project name with `docker volume ls` if
Compose was ever run with a non-default `-p`/`COMPOSE_PROJECT_NAME`
override, since that changes the prefix.) This permanently destroys the
database and object storage contents on this host — there is no undo
once these commands succeed, which is exactly why steps 1-3 must be
verified complete first.

### Archival export format

**What already exists, cited rather than reinvented:** this repository
already has two real, working examples of a durable, application-portable
export shape: `ExportBundleAssembler`'s JSON/CSV pair (US-008, scoped to
one data subject) and `RopaController`'s CSV (US-013, scoped to the whole
organisation's processing activities). Both share the same underlying
design instinct worth carrying forward: plain JSON for structured records
with nested relationships, plain CSV for flat tabular data, both readable
by any tool decades from now without this application, a database
connection, or even necessarily a computer with internet access.

**The genuine gap, stated plainly rather than silently filled or
ignored:** there is no mechanism today that extends that same JSON/CSV
approach to a *full-instance* scope — one export covering every consent
record, every DSAR and its history, every retention execution, every
deletion certificate, and the audit log, all at once, self-describing and
independent of this specific application's continued existence or of
PostgreSQL's own dump-format compatibility across major versions (the real
durability weakness of relying on `pg_dump` alone as a permanent archive
format — a `pg_dump` from PostgreSQL 16 is not guaranteed to `pg_restore`
cleanly into whatever PostgreSQL major version is current when someone
actually needs to read it back, years later; plain-SQL-text remains
*readable* by a human indefinitely, per step 3b, but not necessarily
mechanically *restorable* into a future Postgres without care).

**Proposed shape for a future session** (not built here — this is a
genuinely new, non-trivial feature, not a "trivial missing piece"):
a `php artisan privacy-forge:export-instance` command that walks every
first-class model this document above already cites (`ConsentRecord`,
`ConsentPurpose`, `ConsentNotice`, `DsarRequest`, `ExportBundle` metadata
(not the ciphertext-only bundle itself), `RetentionPolicy`,
`RetentionExecution`, `DeletionCertificate`, `AuditLogEntry`) and emits
one JSON file and one CSV file per table into a single timestamped
directory (or a signed, encrypted archive, matching the existing
`ExportBundleAssembler` pattern) — reusing the exact JSON/CSV shaping
conventions those two existing classes already established, rather than
inventing a third format. This is proposed here as a concrete backlog
item (see `11-backlog.md`), not implemented, per this session's explicit
scope boundary.

## End-of-life policy

- **Pre-v1.0.0 status.** `SECURITY.md` states plainly that "no versioned
  release exists yet." There is therefore no version currently subject to
  an end-of-life date of its own — this section will gain real content
  once a v1.0.0 ships and a support-window policy for its minor versions
  is decided (that decision itself is not made here; it would need its
  own session).
- **No hosted/SaaS instance to sunset.** ADR-0005 and this repository's
  self-hosted-only scope mean there is no centrally-operated instance
  whose end-of-life this project needs to plan for — every deployment is
  a self-hoster's own instance, retired on their own schedule via the
  Decommissioning procedure above. The "public demo instance" mentioned
  elsewhere in the project's docs (`00-project-brief.md`,
  `12-session-handoff.md`'s open items) is a reviewer-facing convenience
  demo, not a customer-facing product instance — its own reset/decommission
  behaviour is tracked separately as an open MVP item, not duplicated here.
- **What happens if this repository itself stops being maintained**
  (a real portfolio-project consideration, stated honestly): AGPL-3.0
  licensing means any self-hoster already has everything needed to keep
  running and patching their own instance independently, with no
  dependency on this repository's own continued activity — consistent
  with the licence rationale already stated in `README.md`.

## Dependency support horizons

Verified against each project's own published support-lifecycle
information (via `endoflife.date`, a third-party community-maintained
tracker, not each vendor's own primary source page — cross-checking
against the vendor's own release-notes/support-policy pages before relying
on any of these dates for a real production decision is recommended,
matching this project's own established honesty standard around external
claims, e.g. the ASVS-mapping caveat at Session 4). Checked 2026-08-17:

| Dependency | Version actually in use | Source of truth in this repo | Active/security support ends |
|---|---|---|---|
| PHP | `8.3` (`php:8.3-cli` base image, `docker/Dockerfile` line 43; `composer.json` requires `^8.3`) | Dockerfile, composer.json | Active support ended 2025-11-23; **security-only support until 2027-12-31** |
| Laravel | `^12.61.1` (`composer.json`) — **note, corrected here**: `00a-ledger-confirmation.md`'s frozen Session-0 ledger row names "Laravel 11" as the originally-allocated framework; the codebase has since moved to Laravel 12 (`12-session-handoff.md` confirms this as of Session 13 onward). This document cites what is actually installed, not the stale ledger figure — the ledger itself is explicitly "not modified again" per its own governance note, so this correction lives here instead. | composer.json | Laravel 11: EOL 2026-03-12 (**already past** as of this session — moot for this repo since it's not what's installed). Laravel 12: active support ends 2026-08-16 (also already passed as of this session's date, 2026-08-17 — worth a fast follow-up look, see note below); **security-only support until 2027-02-24**. |
| PostgreSQL | `16` (`postgres:16-alpine`, `docker-compose.yml` line 127 — unpinned patch version) | docker-compose.yml | Released 2023-09-14; **security support until 2028-11-09** |
| Redis | `7` (`redis:7-alpine`, `docker-compose.yml` line 143 — unpinned minor/patch; could resolve to 7.0/7.2/7.4 depending on pull time) | docker-compose.yml | The 7.x line's last minor (7.4) has security support until 2029-12-01; the exact date depends on which 7.x patch actually gets pulled, which this unpinned tag does not guarantee — pinning to an exact minor (e.g. `redis:7.4-alpine`) would make this a precise claim rather than a range |
| Node.js | `20.x` (`deb.nodesource.com/setup_20.x`, `docker/Dockerfile` line 96) — **test-tooling only since Session 18's R-07 fix**, not a runtime dependency of the running application at all | docker/Dockerfile (`test-deps` stage) | Not checked in depth this session — it no longer gates the running product's support horizon the way it did before R-07's fix, so it was judged lower-priority than the four dependencies above; check `endoflife.date/nodejs` directly before relying on this line for a real decision |

**Honest note on the Laravel 12 date specifically:** the fetched support
table states Laravel 12's *active* support window ended 2026-08-16 — one
day before this session (2026-08-17). That is close enough to "now" that
it is worth a fast, cheap confirmation in whichever session next touches
dependencies (re-check `endoflife.date/laravel` or Laravel's own
`upgrade.laravel.com`/release-notes page), rather than treated as settled
by this session's one-time check — dates this close to "today" are
exactly the kind of claim that's easy to get subtly wrong (off-by-one on
which side of the date "ends" falls, or the tracker's own data lagging a
real announcement) and cheap to re-verify later. Laravel 12's *security*
support (until 2027-02-24) is not at risk either way.
