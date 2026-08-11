# Project Brief
> Purpose: the single source of truth for what this project is and why it exists.
> Project: privacy-forge (public)
> Last updated: 2026-08-10
> Status: FINALISED — Session 1 (Discovery & Business Framing)

## One-line description
A self-hostable consent, data-subject-request (DSAR), and data-retention
engine for a single organisation, giving a small SaaS team a defensible,
auditable answer to "prove you handle personal data lawfully" under
GDPR/UK-GDPR.

## Problem statement
Small SaaS teams (roughly 2–30 people) accumulate GDPR/UK-GDPR obligations
long before they can afford dedicated privacy tooling (OneTrust, Osano) or a
privacy hire. In practice this means: consent tracked in a spreadsheet or not
at all, data-subject requests handled ad hoc through a shared support inbox
with no SLA and no audit trail, and retention policy that exists as a Notion
page nobody enforces. The failure mode is not a daily inconvenience — it is
invisible until an audit, a regulator enquiry, or a customer's own compliance
due-diligence questionnaire arrives, at which point there is no defensible
evidence of process. The cost of the problem is concentrated and severe, not
distributed and mild, which is exactly the shape of problem this class of
buyer under-invests in until it is too late.

**Why this is worth building as a portfolio piece specifically:** it is a
regulated domain that rewards requirements rigour and audit-trail thinking
over raw feature count — which plays to the two SDLC phases this repo is
built to demonstrate deeply (Requirements Analysis; Retirement, Handover &
Disposal), rather than to volume of screens.

## Target users and stakeholders
- **Primary user:** a technical founder or engineering lead at a small SaaS
  company, acting as the de facto privacy officer. They are not a privacy
  specialist — the product must not assume regulatory literacy beyond what
  a competent engineer picks up from reading GDPR once.
- **Secondary user:** the data subject (customer, employee, or prospect of
  the *deploying* company) who submits a consent action or a DSAR through
  the public-facing portal. This user has zero context on the deploying
  company's internals and must be guided, not assumed to know their rights
  or the process.
- **Stakeholder (non-user):** a future auditor, regulator, or enterprise
  customer's due-diligence reviewer, who consumes the RoPA export and audit
  trail as evidence, not as a UI.
- **Stakeholder (portfolio context):** a reviewer of this GitHub repository —
  effectively a fourth audience, distinct from the product's real users, who
  needs the README and SDLC evidence map to make the same trust judgement in
  minutes that a real auditor would make in hours.

## Business assumptions (validated this session)
- **Deployment model:** self-hosted, single organisation per instance.
  *Validated and confirmed — not multi-tenant.* This matches the target
  buyer (who already runs their own infrastructure) and keeps the ABAC
  design honest: policies scope to *roles and data categories within one
  org*, not to tenant boundaries. Multi-tenant SaaS pricing/isolation is a
  distinct problem, deliberately left to the private-track direction PR02.
- **Regulatory frame:** GDPR/UK-GDPR only for v1. *Validated and confirmed —
  CCPA explicitly out of scope.* GDPR is consent-and-lawful-basis-centric;
  CCPA is opt-out-and-sale-disclosure-centric. Supporting both with equal
  rigour would roughly double the Requirements Analysis workload — the exact
  phase this repo is meant to demonstrate *deeply*, not broadly — and would
  risk producing a shallow pass at both instead of a genuinely defensible
  pass at one. GDPR-only is the higher-integrity choice given a fixed budget.
- **Buyer maturity:** the buyer is a data **controller**, not yet operating
  at a scale where they are also a significant **processor** for others.
  This bounds the RoPA and DSAR-orchestration design to a realistic first
  case.
- **Demo/hosting decision:** a **public hosted demo instance** will exist.
  This is a deliberate, higher-effort choice made to maximise reviewer
  impact, and it creates a hard non-negotiable constraint carried forward
  into Sessions 4 and 8: the demo instance must run on synthetic seed data
  only, in isolated infrastructure, with a spend cap and scoped credentials,
  and must never process a real data subject's actual personal data. This
  is recorded here so a later session cannot quietly relax it.

## Success metrics
1. A stranger can self-host `privacy-forge` and complete a full
   consent → withdrawal → DSAR → export cycle, starting from the README
   alone, in under 15 minutes.
2. 100% of MVP acceptance criteria in `02-requirements.md` trace to a
   specific GDPR article in the Requirements Traceability Matrix (target
   articles: 6, 7, 12–15, 17, 20, 30).
3. Zero critical or high-severity findings from CodeQL and `osv-scanner` at
   the v1.0.0 tag.
4. Every ABAC authorisation decision in the audit log records the policy ID
   that produced it — verified by a dedicated authorisation test asserting
   every route against every role (0 unverified route/role pairs at v1.0.0).
5. The public demo instance passes a manual pre-launch check confirming: no
   real PII present, spend cap configured, credentials scoped, and network
   isolation from any other system — checked and re-confirmed before every
   deploy, not just once.

## Feasibility notes and key risks
- **Risk — scope creep toward "full compliance platform."** Mitigated by the
  explicit non-goals in `01-scope-and-non-goals.md` and by holding to the
  90-hour ship-ability estimate as a hard budget signal, not a suggestion.
- **Risk — ABAC is a genuinely new pattern for this developer.** If Session
  3 architecture work reveals materially more complexity than expected, the
  correct response is a short timeboxed spike, not an open-ended detour —
  protects the learning-budget cap (2 new technologies) and the schedule.
- **Risk — public demo instance handling privacy-sensitive subject matter is
  reputationally unforgiving of mistakes.** A privacy tool whose own demo
  leaks or mishandles data is a worse outcome than not having a demo at all.
  Mitigated by treating the safety checklist in this brief as a launch
  blocker, re-verified at every deploy, not a one-time setup step.
- **Feasibility:** Laravel, Vue/Inertia, PostgreSQL, Redis, and S3-compatible
  storage are established skills for this developer — only the ABAC policy
  engine and the formal ASVS L2 mapping exercise are novel, which keeps
  technical risk contained to two well-bounded areas rather than spread
  across the whole stack.

## Elevator pitch (for the README)
"privacy-forge is what you wish your GDPR compliance looked like: consent
capture with a real audit trail, data-subject requests that actually get
fulfilled on a documented process, and retention policies that delete things
on schedule instead of living in a spreadsheet nobody opens. Self-hosted,
single-organisation, GDPR/UK-GDPR only — deliberately narrow, so that what it
does, it does with evidence."
