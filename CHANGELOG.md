# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added (Session 21, 2026-08-18)
- **Retention policy management UI** (`/admin/retention`): view/create
  data categories and retention policies, with a dry-run preview button
  that makes unmistakably clear it makes no changes (US-010/US-011). No
  real-execution button exists by design — real execution only ever runs
  on the server's own schedule (Session 11 decision, unchanged), never
  from a staff HTTP request.
- **RoPA export UI** (`/admin/ropa`): download the Record of Processing
  Activities as CSV or PDF, format choice visible as two separate
  buttons (US-013).
- **ABAC policy management UI** (`/admin/policies`, stretch): view every
  sensitive action's current policy and its history; editing requires an
  explicit per-policy confirmation before it's enabled, since this edits
  live separation-of-duties logic.
- All three UIs call only pre-existing `GET`/`POST`/`PATCH` endpoints —
  no API contract changes.
- Found and filed two pre-existing spec/implementation gaps while
  scoping this work (not fixed, per this session's UI-only scope): `GET
  /admin/audit-log` is documented in `openapi.yaml` but was never
  implemented (`B-04`); no read endpoint exists for past retention
  execution history or their deletion certificates (`B-05`). See
  `docs/project-memory/11-backlog.md`.

### Documented (Session 20, 2026-08-18)
- **Retroactively decided and documented the Laravel version this
  repository actually runs on.** Forensic investigation traced an
  undocumented "Laravel 11 → 12" drift to commit `97868f1` (Session 5's
  first correction commit): its `composer.json` diff applied a
  CVE-driven `laravel/framework` bump that the *same commit's* own
  `CHANGELOG.md`/handoff entries explicitly said was declined and not
  applied — a self-contradiction between narrated decision and actual
  diff, never caught by any of the 13 sessions since. `composer.lock`
  confirms the codebase has run exclusively on Laravel 12.x (currently
  `v12.66.0`) since Session 6a's first real build — every feature, every
  test, for 14 sessions. See **ADR-0008**
  (`docs/adr/ADR-0008-laravel-12-retroactive-adoption.md`) for the full
  account and the decision to keep Laravel 12 (reverting now would mean
  running code that has never once executed on Laravel 11, for no
  functional benefit). A new CI job (`dependency-governance`) now fails
  any PR that changes the `laravel/framework` constraint without also
  touching `docs/adr/` or the decision log, so this specific failure
  mode can't recur silently. No application code, dependency, or test
  changed — documentation and CI configuration only.

### Fixed
- Post-Session-5 correction: `vendor/` was being shadowed by the
  `app`/`worker` bind mounts in `docker-compose.yml` (no exclusion,
  unlike `frontend`'s handling of `node_modules`) — added an anonymous
  `/var/www/html/vendor` volume to both.
- Post-Session-5 correction: `config/` directory did not exist at all.
  Added the standard Laravel 11 config set; `database.php`'s Redis
  client now explicitly defaults to `predis` (matching the actual
  `composer.json` dependency, since no `redis` PECL extension is
  installed in the app image).
- Post-Session-5 correction: `.env.example` had a blank `DB_PASSWORD`
  while `docker-compose.yml`'s `postgres` service sets a real one —
  fixed to match.

### Declined
- A reported CVE (CVE-2026-48019) requiring a Laravel 11→12/13 major
  version bump, plus cascading Pest/Larastan bumps, was **not** applied.
  Could not be verified — no web search tool available, and
  `packagist.org` is unreachable from the sandbox that built this
  (confirmed by testing, not assumed). Needs human verification with a
  checkable source before any version bump. See
  `docs/project-memory/12-session-handoff.md`.

### Added
- Session 5: Laravel 11 + Vue 3/Inertia application skeleton (no product
  features yet) — `artisan`, `bootstrap/app.php` wired to Laravel's built-in
  `/up` health route, minimal Welcome page proving the Inertia pipeline
  renders end to end.
- Session 5: Docker Compose reference development stack (app, frontend,
  worker, PostgreSQL, Redis, MinIO), all with health checks.
- Session 5: real CI pipeline replacing the Session 0 placeholder — PHP
  lint (Pint), static analysis (Larastan/PHPStan level 8), tests (Pest),
  frontend lint/build, gitleaks, CodeQL, `osv-scanner`, and automated
  OpenAPI contract validation on every PR.
- Session 5: `.env.example` fully wired to prior architecture decisions
  (export-bundle TTL, DSAR rate limit, audit-chain anchor destination,
  demo-mode flag).
- Session 5: one environment smoke test (health check only — feature tests
  begin at Session 6, deliberately kept out of this session's scope).
- Session 4: STRIDE threat model covering 5 trust boundaries and 20 threats,
  including a dedicated Demo Instance Data Safety section (scheduled resets,
  no persistent shared admin credential, connector registration compiled
  out entirely on the demo build) and 4 explicitly accepted risks with
  revisit triggers.
- Session 4: OWASP ASVS L2 control mapping
  (`docs/security/asvs-mapping.md`), with an explicit caveat that exact
  clause numbers require verification against the current standard before
  Session 6 implementation.
- Session 4: ADR-0006 — the ABAC evaluator fails closed by default on any
  error; `policy.update` added to the sensitive-action registry as an
  Owner-only, audit-logged action.
- Session 4: resolved the Session 3 connector-callback anomaly — a
  conflicting terminal-status callback now automatically disables the
  connector pending manual review.
- Session 3: full architecture (system context, container, and 3 sequence
  diagrams), data model (14-entity ERD with invariants and their exact
  enforcement mechanism), and API contracts, including a hand-authored,
  machine-validated OpenAPI 3.1 specification
  (`docs/architecture/openapi.yaml`).
- Session 3: 5 ADRs — ABAC policy model with separation-of-duties as policy
  data; retention dry-run/execution parity via a shared selector; audit-log
  tamper-evidence via hash chain + DB grants + external anchoring; async
  connector webhook contract; single-organisation data model with no
  tenant column.
- Session 3: identified and resolved a backup/retention-TTL conflict —
  export bundles are excluded from long-retention backups so the 72-hour
  signed-URL promise holds in practice, not just in the application layer.
- Session 2: full requirements document (deep phase) — roles/permissions
  matrix, 15 user stories with acceptance criteria, 20 functional
  requirements, 11 numeric NFRs, data classification for all 8 data
  elements, connector integration requirements, and the GDPR Article
  Requirements Traceability Matrix (Arts. 5, 6, 7, 12, 13/14, 15, 17, 20, 24,
  30 — each mapped to specific FRs and named test locations).
- Session 1: finalised project brief with validated business assumptions
  (GDPR/UK-GDPR only, single-tenant, public hosted demo with a mandatory
  synthetic-data safety constraint).
- Session 1: scope and non-goals document, including an 8-item non-goals
  table with reconsider-triggers and a formal "definition of v1 complete."
- Repository governance: framework allocation ledger row confirmed
  (`UNIQUE` — Vue 3 + Laravel 11, no flagship collision).
- Project Memory Pack scaffolded (15-file structure under
  `docs/project-memory/`).
- Session 0 deliverables: ledger confirmation, project brief stub,
  repository skeleton, licence, contribution/security/conduct policies.

Nothing has shipped yet — this project is pre-v0.1.0.
