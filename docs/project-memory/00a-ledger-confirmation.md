# Session 0 — Ledger Confirmation

> Purpose: freeze the technology allocation for this repository before any
> architecture work begins, per Portfolio Governance Rule D1 ("ledger before
> architecture").
> Last updated: 2026-08-08

> **Superseding note (Session 20, 2026-08-18, added above the frozen
> content below rather than editing it — see this file's own closing
> governance note):** the "Primary backend: Laravel 11" row below reflects
> the allocation as it stood at Session 0 and is left as-is for historical
> accuracy. The repository has actually run on Laravel 12 (`^12.61.1`)
> since early in Session 5, through undocumented drift rather than a
> deliberate decision — see **ADR-0008** (`docs/adr/ADR-0008-laravel-12-retroactive-adoption.md`)
> for the full forensic account and the retroactive decision to keep
> Laravel 12. Treat "Laravel 12" as this repository's actual, current,
> decided backend version wherever this row is cited.

## Ledger row (from master Framework Allocation Ledger)

| Field | Value |
|---|---|
| Repository | `privacy-forge` |
| Domain | Data-privacy / consent & DSAR compliance |
| Platform | Web app |
| Primary frontend | Vue 3 (via Inertia) |
| Primary backend | Laravel 11 |
| Primary mobile/desktop | — |
| Language(s) | PHP, TypeScript |
| Key data/infra | PostgreSQL, Redis, S3/MinIO, queues |
| New learning objective | ABAC policy evaluation engine; OWASP ASVS L2 control mapping |
| SDLC phases (deep) | 2. Requirements Analysis · 8. Retirement/Disposal |
| Overlap status | `UNIQUE` |

## Overlap check

- Vue 3 as primary **frontend**: not used as primary frontend by any other
  flagship repository. ✅ No collision.
- Laravel 11 as primary **backend**: not used as primary backend by any other
  flagship repository. ✅ No collision.
- No mobile/desktop framework claimed. N/A.

**Result: PASS.** This repository may proceed past Session 2 (per Rule D1, the
gate is actually "before Architecture," i.e. before Session 3 — recorded here
so Session 3 does not need to re-verify).

## Learning budget check (Rule D3 — max 2 new technologies)

| New technology | Genuinely new? | Counts against budget |
|---|---|---|
| ABAC policy evaluation engine | Yes — first ABAC implementation in the portfolio (R06 uses RBAC) | 1 |
| OWASP ASVS L2 control mapping | Yes — first formal ASVS mapping exercise | 2 |
| Laravel, Vue, Inertia, PostgreSQL, Redis, S3, queues | No — established skills, reused deliberately (see business-fitness rationale in project brief) | 0 |

**Result: PASS.** Exactly 2 new technologies — at budget, not over.

## Deep SDLC phase check (Rule D2 — exactly two)

1. **Requirements Analysis** — chosen because a compliance product lives or
   dies on regulatory traceability; this is the phase where that evidence is
   produced (GDPR article → requirement → test).
2. **Retirement, Handover & Disposal** — chosen because it is the rarest phase
   demonstrated anywhere in the portfolio, and it is literally the product's
   subject matter (the app *is* a data-lifecycle tool).

No third deep phase is claimed. Architecture, Security, Testing, Release, and
Implementation are baseline; Discovery and Operations are intentionally light
(reasons recorded in `docs/SDLC-EVIDENCE.md`, to be completed in Session 9).

## Ship-ability check (Rule D4)

Estimated time to credible v1: 90 hours (from the flagship specification).
Within the ≤120-hour guideline. **Result: PASS.**

## Governance sign-off

- [x] Ledger row confirmed against master register
- [x] Zero collisions
- [x] Learning budget ≤ 2 confirmed
- [x] Exactly two deep SDLC phases chosen
- [x] Ship-ability check passed
- [x] Repository added to Status Board under **Now** (see portfolio governance
      repo — action: add this row manually to `portfolio/STATUS.md`)

**This file is not modified again.** It is the frozen record Session 3 checks
before starting architecture work.
