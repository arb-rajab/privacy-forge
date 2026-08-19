# Release Notes
> Purpose: what changed, for humans
> Project: privacy-forge (public)
> Last updated: 2026-08-18

## Unreleased
### Added
### Changed
### Fixed
### Security
### Migration notes
### Rollback instructions

## v1.0.0 - 2026-08-18

The first tagged release. Everything below is genuinely working end to
end against a real running stack — not a features list of what was
coded, but of what was proven. See `docs/CASE-STUDY.md` for the full
engineering account, `docs/demo/` for real screenshots, and
`docs/project-memory/12-session-handoff.md` for the session-by-session
history behind this tag.

### Added
- **Consent management:** purpose/notice registry with versioned notices,
  an embeddable consent widget proven on a genuine third-party static
  HTML page, capture and withdrawal (US-001–004).
- **DSAR lifecycle:** public intake portal, staff-facing identity
  verification and erasure approval enforced two-person (ADR-0007
  separation-of-duties), async connector dispatch/callback with
  retry/backoff, export bundles via a short-TTL (≤72h) signed URL,
  deletion certificates (US-005–009).
- **Retention policies:** per-data-category rules, a dry-run preview
  structurally guaranteed to select the same records a real run would
  (ADR-0002), scheduled execution, execution history (US-010–012).
- **RoPA export** (CSV/PDF) generated on demand from live data, never a
  stored, independently-drifting copy (US-013).
- **ABAC authorisation** via a custom `PolicyEvaluator` for every
  sensitive action, fail-closed by default on any evaluation error
  (ADR-0006), with a cross-field comparison operator powering
  separation-of-duties (ADR-0007) — exhaustively tested against every
  (role × action) pair (NFR-005).
- **Tamper-evident audit log:** a hash chain plus periodic external
  anchoring (ADR-0003), proven against a real full-chain-rewrite attack
  simulation.
- **Staff authentication:** real session-based login, rate-limited,
  CSRF-protected (R-05).
- A real, working reference connector proving the outbound webhook
  contract end to end, not just against test doubles (R-06).
- A multi-stage production Docker image and a full local, HTTPS-verified
  deployment proof against placeholder infrastructure values (Sessions
  22–24) — see "Not included" below for what this deliberately doesn't
  claim.

### Changed
- Framework version formally decided as Laravel 12.x, retroactively
  correcting 14 sessions of undocumented drift from the frozen Session 0
  "Laravel 11" ledger allocation (ADR-0008). No functional change — the
  codebase has run exclusively on Laravel 12 since its first real build.
- Success Metric #1 (README walkthrough timing) revised to separate
  environment setup time from product-walkthrough time, each measured
  and stated honestly rather than collapsed into one number (Session 18).
- Success Metric #5 and the original demo-hosting decision revised to
  descope real, paid-for public infrastructure for this portfolio build
  (Session 24) — the safety controls that decision depended on remain
  real and verified; only "is this live and public" changed.

### Fixed
Three are worth naming for what they teach, not just that they were
fixed — full accounts in `docs/CASE-STUDY.md`:
- `RetentionSelector` re-selected and re-certified already-anonymised
  records on every scheduled run, because it never excluded rows a prior
  run had already acted on (Session 12).
- The export bundle's signed-URL/TTL machinery was built and tested from
  Session 8 onward, but nothing on the data-subject-facing path ever
  actually minted the URL — the feature was correct in isolation and
  unreachable end to end until Session 10 wired it through the existing
  DSAR status link.
- A Docker bind mount silently shadowed `vendor/` with no exclusion,
  in the same commit that (separately) introduced the undocumented
  Laravel 12 drift later resolved by ADR-0008 (Session 5's first
  correction commit).

### Security
- OWASP ASVS L2 control mapping (`docs/security/asvs-mapping.md`) and a
  STRIDE threat model covering 5 trust boundaries and 20 threats
  (`docs/project-memory/06-security-threat-model.md`).
- CI enforces zero critical/high findings from CodeQL and `osv-scanner`
  on every PR (Success Metric #3).
- A `dependency-governance` CI job (ADR-0008) fails any PR that changes
  the `laravel/framework` constraint without a corresponding ADR or
  decision-log entry — closing the exact failure mode that produced the
  undocumented Laravel 12 drift.

### Not included (by explicit, recorded decision — not a silent gap)
- **No live, publicly-reachable demo URL.** Real cloud provisioning is
  explicitly out of scope for this portfolio build (Session 24); the
  full deployment automation and every applicable Demo Instance Data
  Safety control are instead proven against a local, placeholder-domain
  HTTPS deployment. See `docs/project-memory/09-decision-log.md`'s
  Session 24 entry.
- **No automated browser-driven end-to-end proof of the admin
  dashboard's client-side rendering.** The Pest Browser Testing suite's
  Docker-launched Chromium hangs reliably on this host class
  (`R-08`, accepted residual risk, Session 19) — the backend contract
  every admin button calls is instead proven via a real HTTP walkthrough
  against the live stack. The screenshots in `docs/demo/` were captured
  by a different, non-Docker mechanism specifically to work around this.
- **`R-01`** (audit log lacks DB-level grant revocation) remains open —
  the hash chain provides tamper-evidence independently; closing this
  fully needs a second, lower-privileged migration-only database role.

### Known debt going into v1.0.0 (stated plainly, not silently carried)
This release closes R-02 through R-06 for real (see "Fixed"/"Added"
above and `docs/CASE-STUDY.md`) and formally accepts R-08 as a residual
risk. **`R-07`** (Dockerfile cold-clone rebuild time) was still an open,
composed-estimate-only figure at tag time; it is now genuinely closed
as of 2026-08-19 — a real, unbroken, from-true-zero clean rebuild
measured **423 seconds (~7.1 minutes)**, with the full test suite
(187/187) passing against that exact freshly-built image, comfortably
under the 900-second budget (see `10-risk-register.md`'s R-07 entry
for the full account, including three earlier attempts that failed on
transient host network issues, reported honestly rather than hidden).
It does **not** close everything — the following remain open,
tracked, and non-blocking by explicit decision, not by omission:
- **`R-01`** — DB-level grant revocation gap on the audit log, open
  since it was filed; see "Not included" above.
- **`B-01`–`B-03`** — three open backlog items (full-instance archival
  export, a retention-policy uniqueness race, a weekly `osv-scanner`
  re-run trigger, per `11-backlog.md`). None block v1.0.0's own scope;
  all are real, itemized, and not silently dropped. `B-04`/`B-05`
  (audit-log read endpoint, retention execution history endpoint) were
  actually closed at Session 22, and `B-06` (production image) at
  Sessions 23–24 — all three predate this tag. This release note
  originally miscounted them as still open at tag time; corrected
  2026-08-19 after a staleness audit against real commit history.
  `B-07`/`B-08` are already closed (Session 24).

### Migration notes
This is the first tagged release — there is no prior schema to migrate
from. A fresh instance runs all 18 migrations via `php artisan migrate`
per the README quickstart.

### Rollback instructions
No prior release exists to roll back to. For an existing v1.0.0 instance,
standard Laravel practice applies: `php artisan migrate:rollback` is
supported for every migration in this release (none are irreversible),
and nightly encrypted PostgreSQL backups are the documented recovery path
for anything rollback alone can't fix — see
`docs/project-memory/08-deployment-and-operations.md`'s backup/restore
section.
