# SDLC Evidence Map

**Deep phases:** 2. Requirements Analysis · 8. Retirement, Handover & Disposal
**Baseline phases:** 3. Architecture & Design (includes security/threat modelling) · 4. Implementation · 5. Verification & Testing · 6. Release & Deployment
**Intentionally light:** 1. Discovery & Planning (the regulatory problem is well-understood, not something to discover from scratch); 7. Operations & Maintenance (ops depth is demonstrated deeply elsewhere in the portfolio, in R03 `pulsewatch`)

| Phase | Depth | Evidence | Location |
|---|---|---|---|
| 1. Discovery & Planning | light | Problem statement, users, business assumptions, feasibility notes | `docs/project-memory/00-project-brief.md` |
| 2. Requirements Analysis | **deep** | Roles matrix, 15 user stories with acceptance criteria, 20 FRs, 11 numeric NFRs, data classification, GDPR Article RTM | `docs/project-memory/02-requirements.md` |
| 3. Architecture & Design | baseline | System context/container diagrams, 3 sequence diagrams, ERD, validated OpenAPI 3.1 spec, 6 ADRs, STRIDE threat model (20 threats across 5 trust boundaries), OWASP ASVS L2 mapping | `docs/project-memory/03-architecture.md`, `04-data-model.md`, `05-api-contracts.md`, `06-security-threat-model.md`, `docs/adr/`, `docs/architecture/openapi.yaml`, `docs/security/asvs-mapping.md` |
| 4. Implementation | baseline | Reproducible dev environment (Docker Compose), coding standards (Pint, PHPStan/Larastan level 8), secure configuration (`.env.example` with no real values, reflecting every architecture decision), Git/PR workflow (established Session 0, now has real content to govern) | `docker-compose.yml`, `pint.json`, `phpstan.neon`, `.env.example`, `CONTRIBUTING.md` |
| 5. Verification & Testing | baseline | CI runs tests automatically (currently one environment smoke test — feature tests begin at Session 6); test framework (Pest) wired end-to-end | `tests/`, `.github/workflows/ci.yml` |
| 6. Release & Deployment | baseline | CI pipeline: lint, static analysis, tests, gitleaks, CodeQL, osv-scanner, OpenAPI validation — all running automatically on every PR. Containerisation (Dockerfiles for app/frontend). Full deployment procedure and IaC still pending (Session 8) | `.github/workflows/ci.yml`, `docker/Dockerfile`, `docker/Dockerfile.frontend` |
| 7. Operations & Maintenance | light | Not yet started | — |
| 8. Retirement, Handover & Disposal | **deep** | Populated at Session 19: data-export/portability, retention-and-deletion schedule, and deletion-certificate mechanisms are all cited against real, existing, tested code (`ExportBundleAssembler`/`ExportBundleController` for US-008; `RetentionSelector`/`RetentionExecutor` + ADR-0002 for US-010/011/012; `DeletionCertificate`'s DB-enforced "exactly one source" CHECK constraint for US-009/US-012) rather than re-described. Genuinely new content: a concrete, command-by-command instance-decommissioning runbook (final RoPA export, final audit-chain `verifyAnchors()` verification, data-export options, secure disposal) and an archival-format assessment that explicitly names a full-instance export mechanism as a real, unbuilt gap (proposed as a future backlog item, not built under this session's scope) rather than glossing over it. Dependency support horizons (PHP 8.3, Laravel 12 — corrected from the frozen Session-0 ledger's stale "Laravel 11", PostgreSQL 16, Redis 7) verified against `endoflife.date` with dates cited, not guessed. | `docs/project-memory/14-maintenance-and-retirement.md`, `docs/adr/ADR-0002-retention-dry-run-parity.md`, `docs/adr/ADR-0005-single-organisation-data-model.md` |

## Why some phases are light

**Discovery & Planning** is intentionally concise: the regulatory problem
(small SaaS companies under-investing in GDPR compliance until an audit
forces the issue) is well-documented and well-understood territory, not
something requiring extensive original user research to establish. The
budget saved here is spent on Requirements Analysis instead, which is where
this repository's actual depth commitment lies.

**Operations & Maintenance** is intentionally baseline rather than deep: a
single-node reference deployment with standard logging/health-checks is
proportionate to this repo's scope, and full operational depth (dashboards,
runbooks, incident response, SLO-driven alerting) is deliberately
demonstrated elsewhere in the portfolio — in `pulsewatch` (R03) — rather than
duplicated here. This repo's second deep commitment is Retirement, Handover
& Disposal instead, which is the rarer and more distinctive phase to
demonstrate.

