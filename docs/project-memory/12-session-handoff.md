# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 1 — Project Discovery & Business Framing**
- Objective: Validate every assumption in the draft brief with real reasoning, and produce the finalised project brief plus scope/non-goals document.
- Status: **complete**

## Work completed
- Resolved three open discovery questions with the project owner:
  - **Regulatory scope: GDPR/UK-GDPR only** (CCPA explicitly excluded — recorded with reasoning and a reconsider-trigger).
  - **Demo strategy: public hosted instance** (higher effort, higher reviewer impact — accepted with a hard safety constraint attached).
  - **Multi-tenancy: no** — single organisation per instance, matching the self-host model; multi-tenancy deliberately deferred to private-track direction PR02.
- Rewrote `00-project-brief.md` in full: problem statement, users/stakeholders, and business assumptions now carry validated reasoning rather than draft placeholders.
- Added a fifth success metric specifically covering the public-demo safety constraint (synthetic data, isolation, spend cap) — this is now a measured, re-checked-at-every-deploy requirement, not a one-off setup note.
- Produced `01-scope-and-non-goals.md`: an 8-row non-goals table (each with a reconsider-trigger), an explicit MVP boundary checklist, a deferred-to-backlog list, and a formal "definition of v1 complete."

## Files created or changed
- `docs/project-memory/00-project-brief.md` — rewritten in full (no draft markers remain). Supersedes the Session 0 stub entirely.
- `docs/project-memory/01-scope-and-non-goals.md` — new. First complete non-goals table in the repo.
- `docs/project-memory/12-session-handoff.md` — this file, replacing the Session 0 handoff.

## Decisions made
- **GDPR/UK-GDPR only, no CCPA** — must not be silently reversed without reopening the 90-hour ship-ability estimate and the Requirements Analysis scope, since CCPA's opt-out model is structurally different from GDPR's consent model, not just a translation exercise.
- **Single organisation per instance, no multi-tenancy** — must not be silently reversed; multi-tenancy is deliberately reserved for the private-track direction PR02, and introducing it here would blur that public/private boundary and add tenant-isolation testing this repo's scope doesn't budget for.
- **Public hosted demo instance, with a mandatory safety constraint** — the constraint (synthetic data only, isolated infra, spend cap, scoped credentials, re-verified at every deploy) is now a recorded requirement, not a suggestion. Sessions 4 (security) and 8 (deployment) must both explicitly satisfy it; neither should treat it as already handled by the other.
- These three decisions are recorded here and in the brief itself; they are not yet ADRs (ADRs begin at Session 3) but should be promoted to ADR-0001 through ADR-0003 at that point rather than re-litigated.

## Validation performed
- Commands run: none (this was a documentation/discovery session — no code exists yet).
- Tests run and results: not applicable.
- Lint / static analysis / security scan results: not applicable.
- Manual checks performed: verified the non-goals table doesn't silently contradict the MVP boundary checklist (cross-checked line by line); verified all "Would reconsider if" triggers are concrete conditions rather than "maybe later."

## Open questions and risks
- **No open questions remain from Session 0.** All three were resolved this session.
- **Risk carried forward (unchanged):** ABAC is a genuinely new pattern for this developer — if Session 3 reveals more complexity than expected, use a timeboxed spike rather than an open-ended detour.
- **Risk carried forward, now sharpened:** the public demo instance decision raises the stakes on the Session 4 threat model and Session 8 deployment work — a privacy tool whose own public demo mishandles data is a worse portfolio outcome than having no demo. This is now an explicit success metric (#5 in the brief), not just a note.
- **No blockers.** Session 2 can start immediately.

## Next recommended session
- Proposed session title: **Session 2 — Requirements and MVP Scope**
- Single objective: Turn the validated brief and non-goals into testable requirements — user stories with acceptance criteria, numeric NFRs, a roles/permissions matrix, data classification, and the GDPR-article requirements traceability matrix.
- Inputs required: `00-project-brief.md`, `01-scope-and-non-goals.md`, this handoff.
- Expected deliverables: `02-requirements.md` complete per the standard template, with an RTM covering GDPR articles 6, 7, 12–15, 17, 20, and 30 at minimum.
- Definition of done: Gate 2→3 checklist satisfied — every MVP feature has acceptance criteria, NFRs are numeric (not "should be fast"), roles/permissions matrix and data classification exist, integration requirements and constraints are written down. Since Requirements Analysis is one of this repo's two *deep* phases, this session should be expected to run longer or split (2a/2b) rather than be rushed to fit a single sitting.

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent, DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 1 complete, pushed to https://github.com/arb-rajab/privacy-forge

**Problem being solved:** Small SaaS companies accumulate GDPR/UK-GDPR obligations before they can afford dedicated privacy tooling or headcount, resulting in undocumented, indefensible handling of consent, data-subject requests, and retention.

**Users:** Primary — a technical founder/engineering lead acting as de facto privacy officer, assumed to have no specialist regulatory literacy. Secondary — the data subject using the public request portal, assumed to have zero context on the deploying company's internals.

**Current stack:**
- Frontend: Vue 3 via Inertia
- Backend: Laravel 11
- Data: PostgreSQL, Redis, S3-compatible object storage
- Infra: Docker Compose (built at Session 5), GitHub Actions
- Testing: Pest, Playwright (planned — not yet implemented)

**Architecture decisions that must not be reversed:**
- Licence is AGPL-3.0 (hostable app, not a library) — Session 0.
- Framework pair fixed: Vue 3 + Laravel 11, frozen against the portfolio-wide ledger — Session 0.
- Exactly two deep SDLC phases: Requirements Analysis, Retirement/Handover & Disposal — Session 0.
- **GDPR/UK-GDPR only, no CCPA** — Session 1.
- **Single organisation per instance, no multi-tenancy** — Session 1.
- **Public hosted demo instance is committed, with a mandatory safety constraint** (synthetic data only, isolated infra, spend cap, scoped credentials, re-verified every deploy) — Session 1.

**Implementation state:**
- Done: repository skeleton, licence, governance docs, empty Project Memory Pack (except files 00–01), finalised project brief, finalised scope/non-goals.
- In progress: nothing mid-flight.
- Not started: requirements document and everything downstream — no application code exists yet.

**Constraints and non-goals:**
- Max 2 new technologies for this repo (ABAC policy engine, ASVS L2 mapping) — already at cap.
- Full non-goals list (8 items, each with a reconsider-trigger) is in `01-scope-and-non-goals.md` — do not silently reintroduce any of: cookie-banner CMP, legal-advice content, non-GDPR jurisdiction packs, multi-tenancy, enterprise SSO/SCIM, DPIA automation, AI-generated legal-basis recommendations, or production third-party connectors.

**Deep SDLC phases for this repo:** Requirements Analysis, Retirement/Handover & Disposal
**Intentionally light phases:** Discovery (already concluded, deliberately concise), Operations (baseline only — depth lives in R03 `pulsewatch` elsewhere in the portfolio)

**Task for this session (single objective):**
Produce `02-requirements.md`: user stories with acceptance criteria for every MVP boundary item, numeric NFRs, a roles/permissions matrix, data classification table, integration requirements, constraints, and a GDPR-article requirements traceability matrix (target articles: 6, 7, 12–15, 17, 20, 30).

**Definition of done:**
- Every item in the MVP boundary checklist (`01-scope-and-non-goals.md`) has at least one user story with Given/When/Then acceptance criteria.
- Every NFR has a numeric target and a stated verification method.
- The RTM has zero MVP features without a mapped GDPR article, and zero mapped articles without a corresponding test reference (even if the test doesn't exist yet — reference where it will live).

**Files to attach or paste:**
- `docs/project-memory/00-project-brief.md`
- `docs/project-memory/01-scope-and-non-goals.md`
- `docs/project-memory/12-session-handoff.md` (this file)

**Ground rules:** Do not change the stack. Do not introduce a third new technology. Do not expand the deep-SDLC-phase count beyond two. Do not reopen the GDPR-only, single-tenant, or public-demo decisions — treat them as settled unless the project owner explicitly reopens them. Ask before introducing any new dependency or scope item not already anticipated above.
