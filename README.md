# privacy-forge

> **Status:** 🚧 Session 5 complete — environment, CI, and a minimal running
> skeleton. No product features implemented yet. See
> [`docs/project-memory/12-session-handoff.md`](docs/project-memory/12-session-handoff.md)
> for current state and next steps.

A self-hostable consent, data-subject-request (DSAR), and data-retention
engine for small SaaS teams who need a defensible answer to "prove you handle
personal data lawfully" — without an enterprise compliance-platform budget.

## What this demonstrates

- **Requirements Analysis (deep):** full regulatory traceability from GDPR
  article → requirement → test.
- **Retirement, Handover & Disposal (deep):** data export, retention, and
  deletion aren't an afterthought — they're the product.
- Attribute-based access control (ABAC), OWASP ASVS L2 mapping, tamper-evident
  audit logging.

Stack: Laravel 11 · Vue 3 (Inertia) · PostgreSQL · Redis · S3-compatible
storage.

## Project status

This repository is built through a session-based workflow. Current phase:
**Session 5 (Environment, Standards, CI Baseline) — complete.** Next:
Session 6 (Feature Implementation — first vertical slice).

Full portfolio context: this is a flagship repository in a broader
public/private software portfolio. See `docs/project-memory/` for the
complete project memory pack, and `docs/SDLC-EVIDENCE.md` (populated at
Session 9) for the phase-by-phase evidence map.

## Quickstart

```bash
git clone https://github.com/arb-rajab/privacy-forge.git
cd privacy-forge
cp .env.example .env
docker compose up --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Then visit `http://localhost:8000`. See `CONTRIBUTING.md` for the full
development workflow, including running tests, lint, and static analysis.

**No features are implemented yet** — Session 5 (this state) establishes
the environment and CI baseline only. Feature implementation begins at
Session 6, against the API contract already designed and validated at
`docs/architecture/openapi.yaml`.

## Documentation

- [`docs/project-memory/`](docs/project-memory/) — brief, requirements,
  architecture, security, testing, operations, decisions, risks, backlog,
  handoff, release notes, maintenance/retirement plan
- [`docs/adr/`](docs/adr/) — architecture decision records
- [`SECURITY.md`](SECURITY.md) — vulnerability disclosure policy

## Non-goals

See `docs/project-memory/01-scope-and-non-goals.md` (produced in Session 1).

## Licence

AGPL-3.0 — see [`LICENSE`](LICENSE). Rationale: this is a hostable
application, not a library; AGPL ensures modifications to a hosted version
remain shareable.
