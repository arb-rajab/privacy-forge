# privacy-forge

> **Status:** 🚧 Session 0 complete — governance and repository skeleton only.
> No application code yet. See [`docs/project-memory/12-session-handoff.md`](docs/project-memory/12-session-handoff.md)
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
**Session 0 (Governance) — complete.** Next: Session 1 (Discovery).

Full portfolio context: this is a flagship repository in a broader
public/private software portfolio. See `docs/project-memory/` for the
complete project memory pack, and `docs/SDLC-EVIDENCE.md` (populated at
Session 9) for the phase-by-phase evidence map.

## Quickstart

_Not yet available — implementation begins at Session 5 (environment & CI
baseline)._

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
