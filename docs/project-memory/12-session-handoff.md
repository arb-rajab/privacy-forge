# Session Handoff

## Project
- Repository: `privacy-forge`
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 0 — Portfolio Governance & Technology Allocation**
- Objective: Confirm the ledger row, learning budget, and non-goals before any architecture work begins.
- Status: **complete**

## Work completed
- Confirmed framework allocation: **Vue 3 (frontend) + Laravel 11 (backend)** — verified `UNIQUE`, zero collisions against the master ledger.
- Confirmed learning budget: exactly 2 new technologies (ABAC policy engine, OWASP ASVS L2 mapping) — at cap, not over.
- Confirmed the two deep SDLC phases for this repo: **Requirements Analysis** and **Retirement, Handover & Disposal**.
- Confirmed ship-ability estimate (90h) is within the 120h guideline.
- Created repository skeleton: directory structure, licence (AGPL-3.0), `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `CHANGELOG.md`, `README.md` (status: skeleton).
- Scaffolded the full 15-file Project Memory Pack under `docs/project-memory/`.
- Wrote a draft `00-project-brief.md` (marked STUB — to be validated and finalised in Session 1).
- Added GitHub issue templates (bug, feature, security), a PR template, and a CI placeholder workflow so branch protection can be enabled from day one.
- Initialised git and made the first commit (see below).

## Files created or changed
- `docs/project-memory/00a-ledger-confirmation.md` — frozen governance record; Session 3 checks this before starting architecture.
- `docs/project-memory/00-project-brief.md` — draft brief; **will be rewritten, not appended to, in Session 1**.
- `docs/project-memory/01-scope-and-non-goals.md` through `14-maintenance-and-retirement.md` — empty templates from the standard scaffold, ready for their respective sessions.
- `docs/SDLC-EVIDENCE.md` — empty template, populated at Session 9.
- `README.md` — skeleton with status banner; will be rewritten at Session 9.
- `LICENSE` — AGPL-3.0 (rationale: hostable application, not a library — recorded so this isn't silently changed later).
- `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `CHANGELOG.md` — standard governance docs.
- `.github/workflows/ci.yml` — placeholder only; full pipeline (lint, static analysis, tests, security scans, SBOM) is a Session 5 deliverable.
- `.github/PULL_REQUEST_TEMPLATE.md`, `.github/ISSUE_TEMPLATE/*.yml` — contribution scaffolding.
- `.gitignore` — standard Laravel/Node exclusions plus `.env`.
- `scaffold-memory-pack.sh` — copied into the repo so future sessions (or other portfolio repos) can regenerate the pattern.

## Decisions made
- **Licence: AGPL-3.0**, not MIT — because this is a hostable application (per the portfolio rule: MIT for libraries/tools, AGPL for hostable apps). Should not be silently changed without a recorded reason.
- **Framework allocation is frozen** at Vue 3 + Laravel 11. Must not be silently reversed — doing so would require reopening the entire ledger and re-checking all 12 flagship rows for new collisions.
- **Exactly two deep SDLC phases** (Requirements, Retirement) are committed. A third should not quietly creep in during later sessions (Rule D2).
- No formal ADR yet — ADRs begin at Session 3. This session's decisions are governance decisions, not architecture decisions, and are recorded here and in `00a-ledger-confirmation.md` instead.

## Validation performed
- Commands run: `bash -n scaffold-memory-pack.sh` (syntax check, passed), `git status`, `git log`.
- Tests run and results: none applicable — no application code exists yet.
- Lint / static analysis / security scan results: none applicable yet; the CI workflow is a placeholder that only checks out the repo.
- Manual checks performed: verified LICENSE file downloaded correctly (checked header "GNU AFFERO GENERAL PUBLIC LICENSE" and footer reference to gnu.org — not just YAML frontmatter); verified all 15 memory-pack files were created; verified ledger overlap check against the master register in `00-portfolio-strategy.md`.

## Open questions and risks
- **Open question:** should the CCPA-support claim in the draft brief be kept in v1 scope or explicitly deferred? Needs a decision in Session 1 — currently drafted as "directional, not certified."
- **Risk:** ABAC is a genuinely new pattern for this developer. If Session 3 architecture work reveals it's a bigger lift than expected, consider a short timeboxed spike before committing the ADR, rather than an open-ended detour (protects the 90h ship-ability estimate).
- **Risk (portfolio-level, not repo-level):** this repo occupies one of the two "Now" slots per the WIP-limit-of-2 governance rule. Confirm with the Status Board owner that no other public-track repo is concurrently active.
- **No blockers.** Session 1 can start immediately.

## Next recommended session
- Proposed session title: **Session 1 — Project Discovery & Business Framing**
- Single objective: Validate (or revise) every assumption in the draft brief with real reasoning, and produce the finalised `00-project-brief.md` plus `01-scope-and-non-goals.md`.
- Inputs required: this handoff; `00a-ledger-confirmation.md`; the draft `00-project-brief.md`.
- Expected deliverables: finalised project brief (no "draft" markers remaining); scope and non-goals document; 5 concrete success metrics; explicit MVP boundary.
- Definition of done: Gate 1→2 checklist satisfied (problem statement, target users, stakeholders, assumptions, risks, feasibility note, success metrics, MVP boundary, non-goals — all written and no longer marked draft).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable consent, DSAR, and data-retention engine for small SaaS teams
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 0 complete

**Problem being solved:** Small SaaS companies accumulate GDPR/CCPA obligations before they can afford dedicated privacy tooling or headcount, leading to ad hoc, undocumented handling of consent, data-subject requests, and retention.

**Users:** Primary — a technical founder/engineering lead acting as de facto privacy officer. Secondary — the data subject using the public request portal.

**Current stack:**
- Frontend: Vue 3 via Inertia
- Backend: Laravel 11
- Data: PostgreSQL, Redis, S3-compatible object storage
- Infra: Docker Compose (to be built at Session 5), GitHub Actions
- Testing: Pest, Playwright (planned — not yet implemented)

**Architecture decisions that must not be reversed:**
- Licence is AGPL-3.0 (hostable app, not a library).
- Primary frontend/backend framework pair is fixed (Vue 3 + Laravel 11) — this is frozen against the portfolio-wide framework allocation ledger; changing it requires reopening ledger governance, not just a local decision.
- Exactly two deep SDLC phases for this repo: Requirements Analysis, Retirement/Handover/Disposal. Do not let a third phase (e.g., full Operations depth) creep in — that's deliberately baseline here, since R03 (`pulsewatch`) carries that depth elsewhere in the portfolio.

**Implementation state:**
- Done: repository skeleton, licence, governance docs, empty Project Memory Pack, draft (unvalidated) project brief.
- In progress: nothing mid-flight.
- Not started: everything else — no application code exists.

**Constraints and non-goals:**
- Max 2 new technologies for this repo (ABAC policy engine, ASVS L2 mapping) — already at that cap; do not introduce a third new technology.
- Explicit non-goals to be finalised in this session but already anticipated: cookie-banner CMP for marketing sites, legal advice/templates, multi-jurisdiction rule packs beyond GDPR, enterprise SSO/SCIM, DPIA workflow automation.

**Deep SDLC phases for this repo:** Requirements Analysis, Retirement/Handover & Disposal
**Intentionally light phases:** Discovery (kept concise — the regulatory problem is well-understood, not something to discover from scratch) and Operations (baseline only — ops depth lives in R03 elsewhere in the portfolio)

**Task for this session (single objective):**
Conduct project discovery: validate the draft problem statement, users, and assumptions; identify real risks and success metrics; define the MVP boundary and non-goals.

**Definition of done:**
- `00-project-brief.md` rewritten with no "draft" markers, every section validated with actual reasoning (not assumed).
- `01-scope-and-non-goals.md` produced with an explicit non-goals table (reason for exclusion + condition that would reconsider it).
- 5 concrete, checkable success metrics defined.
- MVP boundary stated as a bullet list a reviewer could tick off.

**Files to attach or paste:**
- `docs/project-memory/00-project-brief.md` (current draft)
- `docs/project-memory/00a-ledger-confirmation.md`
- `docs/project-memory/12-session-handoff.md` (this file)

**Ground rules:** Do not change the stack. Do not introduce a third new technology. Do not expand the deep-SDLC-phase count beyond two. Ask before introducing any new dependency or scope item not already anticipated above.
