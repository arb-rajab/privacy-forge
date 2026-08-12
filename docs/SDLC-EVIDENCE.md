# SDLC Evidence Map

**Deep phases:** 2. Requirements Analysis · 8. Retirement, Handover & Disposal
**Baseline phases:** 3. Architecture & Design · 4. Security & Privacy Design · 5. Implementation · 6. Release & Deployment
**Intentionally light:** 1. Discovery & Planning (the regulatory problem is well-understood, not something to discover from scratch); 7. Operations & Maintenance (ops depth is demonstrated deeply elsewhere in the portfolio, in R03 `pulsewatch`)

| Phase | Depth | Evidence | Location |
|---|---|---|---|
| 1. Discovery & Planning | light | Problem statement, users, business assumptions, feasibility notes | `docs/project-memory/00-project-brief.md` |
| 2. Requirements Analysis | **deep** | Roles matrix, 15 user stories with acceptance criteria, 20 FRs, 11 numeric NFRs, data classification, GDPR Article RTM | `docs/project-memory/02-requirements.md` |
| 3. Architecture & Design | baseline | System context/container diagrams, 3 sequence diagrams, ERD, validated OpenAPI 3.1 spec, 6 ADRs, STRIDE threat model (20 threats across 5 trust boundaries), OWASP ASVS L2 mapping | `docs/project-memory/03-architecture.md`, `04-data-model.md`, `05-api-contracts.md`, `06-security-threat-model.md`, `docs/adr/`, `docs/architecture/openapi.yaml`, `docs/security/asvs-mapping.md` |
| 4. Implementation | baseline | Not yet started | — |
| 5. Verification & Testing | baseline | Not yet started | — |
| 6. Release & Deployment | baseline | Not yet started | — |
| 7. Operations & Maintenance | light | Not yet started | — |
| 8. Retirement, Handover & Disposal | **deep** | Not yet started | — |

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

