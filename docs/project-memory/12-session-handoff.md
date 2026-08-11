# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 2 — Requirements and MVP Scope**
- Objective: Turn the validated brief and non-goals into testable requirements — user stories with acceptance criteria, numeric NFRs, a roles/permissions matrix, data classification, and the GDPR-article requirements traceability matrix.
- Status: **complete**

## Work completed
- Produced the full roles and permissions matrix (Owner, Privacy Manager, Support Staff, Data Subject, Connector) with an explicit note that Privacy Managers cannot approve their own erasure action unassisted (a deliberate separation-of-duties decision, flagged forward to ABAC design in Session 3).
- Wrote 15 user stories (US-001–US-015) covering every MVP boundary item from `01-scope-and-non-goals.md`, each with Given/When/Then acceptance criteria.
- Derived 20 functional requirements (FR-001–FR-020) directly from the user stories, each with a priority and a named (even if not-yet-built) verification location.
- Defined 11 numeric NFRs (NFR-001–NFR-011) — every one has a measurable target and a verification method; none are qualitative ("should be fast").
- Produced the data classification table across all 8 data elements the system will hold, including the explicit "synthetic — not personal data at all" classification for demo seed data.
- Documented integration requirements: the connector webhook contract, with an explicit statement that no real third-party connector ships in v1.
- Produced the **GDPR Article Requirements Traceability Matrix** — the deep-phase centrepiece — covering Articles 5(1)(e), 5(2)/24, 6, 7, 12, 13/14, 15, 17, 20, and 30, each mapped to specific FRs and a named test location.
- Ran the RTM completeness check by hand: confirmed zero MVP features (FR-001–018) are untraced, and zero listed articles lack a mapped FR and named test.

## Files created or changed
- `docs/project-memory/02-requirements.md` — new, full deep-phase requirements document (roles matrix, 15 user stories, 20 FRs, 11 NFRs, data classification, integration requirements, constraints, GDPR RTM).
- `docs/project-memory/12-session-handoff.md` — this file, replacing the Session 1 handoff.

## Decisions made
- **Separation of duties on erasure approval** (implicit in the Privacy Manager row of the roles matrix): a Privacy Manager cannot approve their own DSAR erasure action without a second reviewer. This is a new decision not previously recorded — should be promoted to ADR-0004 or folded into the ABAC ADR at Session 3, not silently dropped.
- **No automated ID-verification provider in v1** (FR-020) — confirmed as staying a documented non-goal rather than being quietly added back now that requirements are being written in detail. Revisit trigger already recorded in `01-scope-and-non-goals.md`.
- **Retention dry-run and real execution must share identical selection logic** (FR-012, US-011) — this is a testability decision with architectural weight: it means the retention engine cannot special-case dry-run behaviour at the query level, only at the side-effect level. Should be respected explicitly as an architecture constraint in Session 3, not rediscovered as a bug later.
- FR numbering was corrected during drafting (an initial duplicate ID clash between the ABAC and audit-log requirements) — resolved before finalising; no downstream references existed yet, so no other document needed updating.

## Validation performed
- Commands run: none (still a documentation-only session — no code exists yet).
- Tests run and results: not applicable.
- Lint / static analysis / security scan results: not applicable.
- Manual checks performed:
  - Cross-checked every MVP boundary checklist item in `01-scope-and-non-goals.md` against the user stories — confirmed 1:1 coverage, no boundary item without a story.
  - Cross-checked every FR against the RTM — confirmed FR-001 through FR-018 all trace to at least one GDPR article; FR-019/020 correctly excluded as integration/non-goal items.
  - Cross-checked every NFR for a numeric target — confirmed none are qualitative statements.

## Open questions and risks
- **Open question:** should the separation-of-duties rule on erasure approval (Privacy Manager cannot self-approve) also apply to export approval, or is erasure's irreversibility the reason it's singled out? Needs a decision in Session 3 when the ABAC policy set is actually designed.
- **Risk carried forward:** ABAC is still a new pattern for this developer, and it now has more surface area than at Session 1 (5 roles, multiple sensitive-action types, an explicit separation-of-duties case). If Session 3 reveals this is materially bigger than expected, use a timeboxed spike rather than an open-ended detour — this is now a more concrete risk than it was at Session 1, not a new one.
- **Risk carried forward, unchanged:** the public demo instance's safety constraint (FR-018, NFR-010) is now doubly load-bearing — it appears in both the FR table and the NFR table — which is intentional (it should be very hard to forget), but Sessions 4 and 8 must both still treat it as their responsibility, not each other's.
- **No blockers.** Session 3 can start immediately.

## Next recommended session
- Proposed session title: **Session 3 — Architecture, Data Design, and API Contracts** (likely to split into 3a/3b given scope)
- Single objective: Design a structure — component boundaries, ERD, API contracts, and ADRs — that satisfies every FR and NFR above, with particular attention to the ABAC policy model, the retention dry-run/execution parity constraint, and the connector contract.
- Inputs required: `02-requirements.md`, `01-scope-and-non-goals.md`, `00-project-brief.md`, this handoff.
- Expected deliverables: `03-architecture.md` (system context + component diagrams, ABAC design, failure handling, backup/recovery), `04-data-model.md` (ERD covering all 8 data-classification entities), `05-api-contracts.md` (consent capture API, DSAR portal API, connector webhook contract), and ≥4 ADRs (at minimum: ABAC policy model, retention dry-run/execution parity, audit-log hash-chain design, and the separation-of-duties question above).
- Definition of done: Gate 3→4 checklist satisfied — diagrams exist, ERD covers every entity in requirements, API contract validates, ≥4 ADRs with trade-offs recorded, scalability and failure-handling notes written, backup/recovery approach stated.

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent, DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 2 complete, pushed to https://github.com/arb-rajab/privacy-forge

**Problem being solved:** Small SaaS companies accumulate GDPR/UK-GDPR obligations before they can afford dedicated privacy tooling or headcount, resulting in undocumented, indefensible handling of consent, data-subject requests, and retention.

**Users:** Owner, Privacy Manager, Support Staff (internal roles); Data Subject (external, via signed links only); Connector (machine-to-machine service account). Full permissions matrix in `02-requirements.md`.

**Current stack:**
- Frontend: Vue 3 via Inertia
- Backend: Laravel 11
- Data: PostgreSQL, Redis, S3-compatible object storage
- Infra: Docker Compose (built at Session 5), GitHub Actions
- Testing: Pest, Playwright (planned — not yet implemented)

**Architecture decisions that must not be reversed:**
- Licence is AGPL-3.0; framework pair fixed (Vue 3 + Laravel 11); exactly two deep SDLC phases (Requirements Analysis, Retirement/Disposal) — Session 0.
- GDPR/UK-GDPR only, no CCPA; single organisation per instance, no multi-tenancy; public hosted demo instance committed with a mandatory synthetic-data-only safety constraint — Session 1.
- **Retention dry-run and real execution must share identical selection logic** (FR-012) — an architecture constraint, not just a test requirement; do not special-case dry-run at the query level.
- **Privacy Managers cannot self-approve erasure** without a second reviewer — a separation-of-duties rule that must be reflected in the ABAC policy design, not bypassed for convenience.
- **No automated ID-verification provider in v1** (FR-020) — manual stub only.

**Implementation state:**
- Done: repository skeleton, licence, governance docs, finalised project brief, finalised scope/non-goals, finalised deep-phase requirements document (roles matrix, 15 user stories, 20 FRs, 11 NFRs, data classification, GDPR RTM).
- In progress: nothing mid-flight.
- Not started: architecture, data model, API contracts, ADRs, and everything downstream — no application code exists yet.

**Constraints and non-goals:**
- Max 2 new technologies for this repo (ABAC policy engine, ASVS L2 mapping) — already at cap.
- Full non-goals list in `01-scope-and-non-goals.md` (8 items with reconsider-triggers) — do not silently reintroduce any of them while designing architecture (e.g., don't accidentally design for multi-tenancy "just in case").

**Deep SDLC phases for this repo:** Requirements Analysis (now complete), Retirement/Handover & Disposal (not yet started)
**Intentionally light phases:** Discovery (concluded), Operations (baseline only)

**Task for this session (single objective):**
Design the system architecture, data model, and API/webhook contracts that satisfy every FR and NFR in `02-requirements.md`, with ADRs covering at minimum: the ABAC policy model (including the separation-of-duties case), the retention dry-run/execution parity constraint, the audit-log hash-chain design, and the connector webhook contract shape.

**Definition of done:**
- System context and component diagrams exist (Mermaid, in-repo).
- ERD covers all 8 data-classification entities from `02-requirements.md`.
- API contract (OpenAPI) validates and covers the consent capture API, DSAR portal API, and connector webhook contract.
- At least 4 ADRs written with context, options considered, decision, trade-offs, and revisit triggers.
- Scalability, failure-handling, and backup/recovery approach documented.

**Files to attach or paste:**
- `docs/project-memory/02-requirements.md`
- `docs/project-memory/01-scope-and-non-goals.md`
- `docs/project-memory/12-session-handoff.md` (this file)

**Ground rules:** Do not change the stack. Do not introduce a third new technology. Do not expand the deep-SDLC-phase count beyond two. Do not reopen the GDPR-only, single-tenant, or public-demo decisions. Do not design around multi-tenancy, CCPA, or automated ID verification even implicitly ("just in case") — these are settled non-goals. Ask before introducing any new dependency or scope item not already anticipated above.
