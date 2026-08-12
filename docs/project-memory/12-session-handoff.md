# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 4 — Security and Privacy Design; Threat Model**
- Objective: Produce the STRIDE threat model and OWASP ASVS L2 mapping, resolving the connector-callback anomaly, the ABAC evaluator's own trust boundary, and the demo instance's data-safety design.
- Status: **complete**

## Work completed
- Resolved the connector-callback anomaly flagged in Session 3: a callback reporting a conflicting terminal status now **automatically disables the connector pending manual review** (T-09), rather than being silently accepted or merely logged.
- Made a new architectural decision not anticipated at Session 3: the **ABAC evaluator fails closed by default** on any error (missing policy, malformed condition, exception, data-access failure) — recorded as **ADR-0006**, not folded silently into ADR-0001, because it's a distinct decision with its own trade-off (availability cost accepted deliberately).
- As a consequence of ADR-0006, added `policy.update` to the sensitive-action registry (Owner-only, audit-logged) — closing the meta-permission gap of "who can change the rules that gate everything else."
- Produced `06-security-threat-model.md`: 5 trust boundaries mapped from the architecture diagrams, 20 STRIDE threats (T-01 through T-20) each with a likelihood/impact rating, a mitigation, and a test reference; 3 cross-boundary abuse cases; a dedicated **Demo Instance Data Safety** section (per the explicit requirement carried from Session 1/3) with 5 concrete controls including scheduled resets and no persistent shared admin credential; and 4 explicitly accepted risks with revisit triggers, matching the honesty standard set in ADR-0003.
- Produced `docs/security/asvs-mapping.md` — an ASVS L2 mapping organised by chapter, with an explicit, honest caveat that exact clause numbers were not asserted from memory and must be pinned against the current ASVS version before Session 6 implementation. This is a deliberate epistemic-honesty choice: asserting false precision would have undermined the credibility this document exists to build.
- Updated `09-decision-log.md` with ADR-0006's summary entry, and `docs/SDLC-EVIDENCE.md` to reflect that the threat model and ASVS mapping are Phase 3 (Architecture & Design) evidence, per the original SDLC framing — not a separate phase.

## Files created or changed
- `docs/adr/ADR-0006-policy-evaluator-fail-closed.md` — new
- `docs/project-memory/06-security-threat-model.md` — full content (was empty template)
- `docs/security/asvs-mapping.md` — new
- `docs/project-memory/09-decision-log.md` — added ADR-0006 entry
- `docs/SDLC-EVIDENCE.md` — updated Phase 3 evidence row
- `docs/project-memory/12-session-handoff.md` — this file, replacing the Session 3 handoff

## Decisions made
- **T-09 resolution:** conflicting terminal-status callbacks trigger automatic connector disablement, not just logging. Must not be silently relaxed to "just log it" at implementation time — the reasoning (a legitimate retry is idempotent and a no-op; only a genuinely conflicting status trips this path) is specific and shouldn't be lost.
- **ADR-0006, fail-closed evaluator:** must not be silently reversed — see the ADR's own consequences section. In particular, a missing/malformed policy must alert (Session 8), not just log quietly, because under fail-closed it manifests as "nothing works" rather than "something insecure happened," which is easy to under-prioritise operationally if it isn't flagged as security-relevant.
- **`policy.update` is Owner-only and audit-logged** — a new addition to the ADR-0001 sensitive-action registry, made necessary by ADR-0006. This should be implemented alongside the other sensitive actions at Session 6, not treated as an afterthought.
- **ASVS clause numbers are deliberately unspecified pending verification** — this is a stated known gap, not an oversight; Session 6 must re-derive them from the primary source rather than anyone assuming the mapping above is already clause-accurate.

## Validation performed
- Commands run: none — this remains a design-phase session, no application code exists yet.
- Tests run and results: not applicable.
- Lint / static analysis / security scan results: not applicable yet.
- Manual checks performed: cross-checked all 5 trust boundaries against the diagrams in `03-architecture.md` — confirmed every boundary shown in the container/context diagrams has at least one corresponding threat in the table; cross-checked every threat with impact rated "Critical" has an explicit, named mitigation (not left as an accepted risk by default) — the 4 items in Accepted Risks were each a deliberate choice, not a gap I failed to address elsewhere.

## Open questions and risks
- **Open question:** should connector auto-disablement (T-09) also trigger an immediate notification to the Owner, or is next-business-day visibility in the admin dashboard sufficient? Leaning toward immediate notification given the "High" impact rating, but this is genuinely a product/ops decision better made at Session 8 when the notification/alerting channel actually exists to decide *how* immediate is practical.
- **Risk carried forward, now resolved:** the fail-closed decision (ADR-0006) means ABAC implementation at Session 6 needs fault-injection tests, not just happy-path tests — flagged explicitly so Session 7's authorisation test suite doesn't only cover the (role × action) matrix under normal conditions.
- **Risk (new):** the ASVS mapping's honesty about unverified clause numbers is correct, but creates a small amount of follow-up debt at Session 6 that could be forgotten if that session gets busy with unrelated implementation work. Worth a one-line reminder in Session 6's own handoff when that session starts.
- **No blockers.** Session 5 can start immediately.

## Next recommended session
- Proposed session title: **Session 5 — Development Environment, Repository Setup, Standards, and CI Baseline**
- Single objective: Make the project reproducible and continuously verified before any feature code exists — Docker Compose stack, coding standards, Git/PR workflow already scaffolded at Session 0 but now needs real content, and a CI pipeline that actually runs lint, static analysis, tests, and the security scans already promised (NFR-004) rather than the Session 0 placeholder.
- Inputs required: all prior Project Memory Pack files, especially `03-architecture.md` (for the container list) and `06-security-threat-model.md` (for which CI security gates are non-negotiable).
- Expected deliverables: a working `docker-compose.yml` reference stack (Laravel, PostgreSQL, Redis, MinIO for S3-compatible storage), `.env.example`, a real GitHub Actions CI workflow (lint, Larastan, Pest scaffold even with zero tests yet, gitleaks, CodeQL, `osv-scanner`), and updated `CONTRIBUTING.md` with actual setup instructions replacing the "not yet available" placeholder.
- Definition of done: Gate 5→6 checklist satisfied — a clean clone reaches a running app via one documented command, CI is green on an empty test suite, `.env.example` contains no real values, and the OpenAPI validation step from Session 3 runs in CI (not just manually, as it did this session).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent, DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 4 complete, pushed to https://github.com/arb-rajab/privacy-forge

**Problem being solved:** Small SaaS companies accumulate GDPR/UK-GDPR obligations before they can afford dedicated privacy tooling or headcount, resulting in undocumented, indefensible handling of consent, data-subject requests, and retention.

**Current stack:**
- Frontend: Vue 3 via Inertia
- Backend: Laravel 11
- Data: PostgreSQL, Redis, S3-compatible object storage (MinIO for local dev)
- Infra: Docker Compose (this session's next step), GitHub Actions
- Testing: Pest, Playwright (planned)
- API: REST + OpenAPI 3.1 (validated spec at `docs/architecture/openapi.yaml`)

**Architecture decisions that must not be reversed:**
- All decisions from Sessions 0–3 (see prior handoffs) remain in force.
- **ADR-0006 (new this session): the ABAC evaluator fails closed on any error** — no code path should ever let an evaluator error resolve to "allow."
- **T-09: conflicting terminal-status connector callbacks trigger automatic connector disablement**, not just a log line.
- **`policy.update` is a registered sensitive action, Owner-only, audit-logged** — do not implement policy editing as a plain CRUD admin screen without this gate.
- **Demo instance controls are specific and non-negotiable:** scheduled resets, no persistent shared admin credential, connector registration compiled out entirely (not just hidden in the UI), visible warning banner, infra-level isolation and spend cap.

**Implementation state:**
- Done: repository skeleton, licence, governance docs, brief, scope/non-goals, deep-phase requirements, full architecture/data-model/API contracts with 6 ADRs, and now the full threat model and ASVS L2 mapping (Sessions 0–4).
- In progress: nothing mid-flight.
- Not started: development environment, CI, and all implementation — no application code exists yet.

**Constraints and non-goals:**
- Max 2 new technologies for this repo (ABAC, ASVS L2 mapping) — at cap; nothing in this session added a new technology, only new decisions within the already-allocated learning objectives.
- Full non-goals list unchanged since Session 1.

**Deep SDLC phases for this repo:** Requirements Analysis (complete), Retirement/Handover & Disposal (not yet started)
**Intentionally light phases:** Discovery (concluded), Operations (baseline only — though note the demo-reset scheduling and connector-disable alerting designed this session will need real implementation at Session 8, which is baseline depth, not light, for this specific repo)

**Task for this session (single objective):**
Build the development environment, repository standards, and CI baseline: Docker Compose stack, `.env.example`, coding standards, and a real CI pipeline enforcing lint, static analysis, tests (even against an empty suite), gitleaks, CodeQL, `osv-scanner`, and OpenAPI spec validation.

**Definition of done:**
- A clean clone reaches a running application via one documented command.
- CI is green on an empty test suite.
- `.env.example` contains no real values (verified, not assumed).
- The OpenAPI validation step runs automatically in CI, not just manually as it did in this session.

**Files to attach or paste:**
- `docs/project-memory/03-architecture.md`
- `docs/project-memory/06-security-threat-model.md`
- `docs/adr/ADR-0006-policy-evaluator-fail-closed.md`
- `docs/project-memory/12-session-handoff.md` (this file)

**Ground rules:** Do not change the stack. Do not introduce a third new technology. Do not reopen any decision from Sessions 0–4 — treat them as settled unless the project owner explicitly reopens them. Do not write feature/business-logic code yet — this session is environment and tooling only. Ask before introducing any new dependency not already implied by the stack above.
