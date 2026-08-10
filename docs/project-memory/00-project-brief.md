# Project Brief
> Purpose: the single source of truth for what this project is and why it exists.
> Project: privacy-forge (public)
> Last updated: 2026-08-08
> Status: STUB — full brief is produced in Session 1 (Discovery & Business Framing)

## One-line description
A self-hostable consent, data-subject-request (DSAR), and data-retention
engine that gives a small SaaS company a defensible, auditable answer to
"prove you handle personal data lawfully."

## Problem statement (draft — refine in Session 1)
Small SaaS teams (2–30 people) accumulate GDPR/CCPA obligations before they
can afford dedicated privacy tooling (OneTrust, Osano) or headcount. The
result is usually a spreadsheet, a shared inbox for data-subject requests, and
no real retention discipline — an audit or a regulator complaint becomes an
existential scramble rather than a documented, routine process.

## Target users and stakeholders (draft)
- **Primary user:** a technical founder or engineering lead at a small SaaS
  company, acting as the de facto privacy officer.
- **Secondary user:** the data subject making a request (customer, employee,
  or prospect) via the public-facing portal.
- **Stakeholder:** a future auditor or regulator who may need the audit trail
  and RoPA export.

## Business assumptions (draft — validate in Session 1)
- The target company is a data controller, not (yet) a large-scale processor.
- GDPR/UK-GDPR is the primary regulatory frame; CCPA support is directional,
  not certified.
- The buyer will self-host or use a small managed instance; there is no
  enterprise SSO/SCIM requirement in v1.

## Why this project exists in the portfolio
- **Technology/learning objective:** ABAC policy evaluation engine; OWASP
  ASVS L2 control mapping.
- **SDLC phases demonstrated deeply:** 2 (Requirements Analysis), 8
  (Retirement, Handover & Disposal).
- **Framework allocation:** Vue 3 (primary frontend) + Laravel 11 (primary
  backend) — confirmed `UNIQUE` in `00a-ledger-confirmation.md`.
- **Private-track relationship:** the reference/companion for PR01 (clinic
  intake) and reusable via the S1 `laravel-consent-guard` package.

## Success metrics (draft — finalise in Session 1)
- A stranger can self-host and complete a full consent → DSAR → export cycle
  from the README in under 15 minutes.
- 100% of MVP acceptance criteria trace to a GDPR article in the requirements
  traceability matrix.
- Zero critical/high findings in CodeQL + `osv-scanner` at v1.0.0.

## Feasibility notes and key risks (draft)
- **Risk:** scope creep toward "full compliance platform." Mitigated by the
  non-goals list (Session 1) and the 90-hour ship-ability budget.
- **Risk:** ABAC is a new pattern for this developer; timebox a learning spike
  before Session 3 architecture work if needed.
- **Feasibility:** all core technologies (Laravel, Vue/Inertia, Postgres,
  Redis, S3) are established skills; only the policy engine and ASVS mapping
  are novel, keeping risk contained.

## Elevator pitch (for the README — draft)
"privacy-forge is what you wish your GDPR compliance looked like: consent
capture with a real audit trail, data-subject requests that actually get
fulfilled, and retention policies that delete things on schedule instead of
living in a spreadsheet nobody opens."

---
**Next session (Session 1) must:** validate every assumption above with the
user, replace "draft" sections with confirmed content, and produce
`01-scope-and-non-goals.md` alongside the finalised version of this file.
