# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 3 — Architecture, Data Design, and API Contracts**
- Objective: Design a structure — component boundaries, ERD, API contracts, and ADRs — that satisfies every FR and NFR from Session 2, resolving the ABAC design, retention parity, audit-log integrity, connector contract, and single-tenancy implementation questions carried forward from Session 2.
- Status: **complete**

## Work completed
- Wrote **5 ADRs** (exceeding the ≥4 gate requirement): ABAC policy model with separation-of-duties as policy data (ADR-0001); retention dry-run/execution parity via a shared selector (ADR-0002); audit-log tamper-evidence via hash chain + DB grants + external anchoring (ADR-0003); async connector webhook contract (ADR-0004); single-organisation data model with no tenant column (ADR-0005).
- Resolved the Session 2 open question on separation of duties: it's enforced as an ABAC policy condition (`actor.id != identity_verified_by`), not a database constraint — because it needs to be auditable and independently testable, and policy versioning is more appropriate than a schema change if the rule ever needs to evolve (e.g. to three-person approval).
- Produced `03-architecture.md`: system context diagram, container diagram, a component responsibility table with an explicit "not responsible for" column, three sequence diagrams (consent capture, DSAR lifecycle showing the partial-failure path, retention dry-run→real execution), scalability rationale (deliberately not over-engineered for a scale this product won't see), a failure-handling table, and backup/recovery design.
- Identified and resolved a subtle correctness issue during backup design: naive indefinite backups of export bundles would quietly contradict the 72-hour signed-URL TTL promise (NFR-007) by letting the data persist in a backup archive forever. Resolved by explicitly excluding export bundles from long-retention backups while keeping audit logs and deletion certificates on the normal long-retention schedule — the opposite retention shape for the opposite reason.
- Produced `04-data-model.md`: full ERD (14 entities) covering every data-classification element from `02-requirements.md`, an invariants table mapping each invariant to its actual enforcement mechanism (DB constraint vs. application logic vs. policy data — deliberately not conflating these), indexing rationale, and a migration/rollback approach requiring every migration's `down()` to be exercised in CI from Session 5.
- Produced `05-api-contracts.md` plus a **hand-authored, machine-validated OpenAPI 3.1 spec** (`docs/architecture/openapi.yaml`) covering consent capture, the DSAR portal, admin actions (including the erasure-approval endpoint that surfaces ABAC denials with the deciding `policy_id`), RoPA/audit export, and the connector callback endpoint. Validated with `openapi-spec-validator` — genuinely passes, not just asserted to.
- Documented the connector webhook/callback contract's outbound and inbound halves, including idempotency handling and an explicitly flagged anomaly case (a callback reporting a different status for an already-terminal task) carried forward to Session 4 as a threat-model item.
- Updated `09-decision-log.md` with short-form entries for all 5 ADRs, each stating why it must not be silently reversed.

## Files created or changed
- `docs/adr/ADR-0001-abac-policy-model.md` — new
- `docs/adr/ADR-0002-retention-dry-run-parity.md` — new
- `docs/adr/ADR-0003-audit-log-tamper-evidence.md` — new
- `docs/adr/ADR-0004-connector-webhook-contract.md` — new
- `docs/adr/ADR-0005-single-organisation-data-model.md` — new
- `docs/project-memory/03-architecture.md` — full content (was empty template)
- `docs/project-memory/04-data-model.md` — full content (was empty template)
- `docs/project-memory/05-api-contracts.md` — full content (was empty template)
- `docs/architecture/openapi.yaml` — new, validated OpenAPI 3.1 spec
- `docs/project-memory/09-decision-log.md` — full content (was empty template)
- `docs/project-memory/12-session-handoff.md` — this file, replacing the Session 2 handoff

## Decisions made
All five ADRs above are now committed decisions, not proposals. In particular:
- **ABAC separation of duties is policy data, not code** (ADR-0001) — changing the approval rule later is a policy update, not a deployment.
- **Retention selection logic exists in exactly one place** (ADR-0002) — no future feature should add a second, "faster" selection path for either dry-run or real execution.
- **Export bundles are excluded from long-retention backups; audit logs and certificates are not** (new decision, surfaced during architecture work, not previously recorded anywhere) — this must be carried into the Session 8 backup configuration exactly as designed, or the 72-hour TTL promise becomes false in practice even though it's true in the application layer.
- **No tenant column exists anywhere** (ADR-0005) — must not be reintroduced "for consistency" in any future session.

## Validation performed
- Commands run: `python3 -m openapi_spec_validator docs/architecture/openapi.yaml` → `OK`.
- Tests run and results: not applicable — no application code exists yet; this session's validation is limited to the specification artifacts it produced.
- Lint / static analysis / security scan results: not applicable yet.
- Manual checks performed: cross-checked every entity in `04-data-model.md`'s ERD against the 8-row data classification table in `02-requirements.md` — confirmed full coverage; cross-checked every FR/NFR from Session 2 against the architecture and ADRs — confirmed each has a corresponding design decision (none were silently dropped); confirmed the OpenAPI spec's `403` responses on ABAC-gated endpoints correctly surface `policy_id` per ADR-0001's audit requirement.

## Open questions and risks
- **Open question:** should the anomaly case (a connector callback reporting a different status for an already-terminal task) be treated purely as a logging/alerting event, or should it also automatically disable that connector pending manual review? Flagged for Session 4's threat model rather than decided here, since it's a security posture question, not an architecture question.
- **Risk carried forward, now more concrete:** ABAC was a documented risk since Session 1; it now has a full design (ADR-0001) but is still unimplemented. If Session 6 implementation reveals the policy-condition JSON schema is harder to make genuinely general than expected, timebox a spike rather than let it sprawl — the design is sound on paper, but paper and code sometimes disagree.
- **Risk (new, surfaced this session):** the backup/TTL interaction (export bundles vs. audit logs having opposite retention shapes) is subtle enough that a future session working on backups in isolation, without re-reading `03-architecture.md`'s Backup and Recovery section, could easily implement a single uniform backup policy "for simplicity" and quietly reintroduce the contradiction this session just resolved. Session 8 should explicitly re-read that section, not just implement backups generically.
- **No blockers.** Session 4 can start immediately.

## Next recommended session
- Proposed session title: **Session 4 — Security and Privacy Design; Threat Model**
- Single objective: Produce the STRIDE-based threat model covering every trust boundary identified so far (public consent/DSAR endpoints, the connector callback endpoint, the admin session boundary, the ABAC evaluator itself), map each threat to a mitigation and an owning test, and produce the OWASP ASVS L2 control mapping that is this repo's second learning objective.
- Inputs required: `03-architecture.md`, `04-data-model.md`, `05-api-contracts.md`, all 5 ADRs, this handoff.
- Expected deliverables: `06-security-threat-model.md` (assets, trust boundaries, STRIDE threats, abuse cases, authN/authZ design summary, secrets management, dependency/supply-chain controls, accepted risks with revisit triggers), plus `docs/security/asvs-mapping.md`.
- Definition of done: Gate 4→5 checklist satisfied — trust boundaries mapped, STRIDE pass complete, abuse cases documented, every threat has an assigned mitigation, secrets-handling approach stated, data-protection decisions recorded. Must explicitly address: the connector callback anomaly case flagged above, the public-demo-instance safety constraint (FR-018/NFR-010) as a named threat category (not just an operational note), and the ABAC evaluator itself as a trust boundary (what happens if it's bypassed or misconfigured).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent, DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 3 complete, pushed to https://github.com/arb-rajab/privacy-forge

**Problem being solved:** Small SaaS companies accumulate GDPR/UK-GDPR obligations before they can afford dedicated privacy tooling or headcount, resulting in undocumented, indefensible handling of consent, data-subject requests, and retention.

**Current stack:**
- Frontend: Vue 3 via Inertia
- Backend: Laravel 11
- Data: PostgreSQL, Redis, S3-compatible object storage
- Infra: Docker Compose (built at Session 5), GitHub Actions
- Testing: Pest, Playwright (planned — not yet implemented)
- API: REST + OpenAPI 3.1 (validated spec at `docs/architecture/openapi.yaml`)

**Architecture decisions that must not be reversed:**
- Licence AGPL-3.0; framework pair fixed (Vue 3 + Laravel 11); exactly two deep SDLC phases (Requirements Analysis, Retirement/Disposal) — Session 0.
- GDPR/UK-GDPR only; single organisation per instance; public hosted demo instance with a mandatory synthetic-data-only safety constraint — Session 1.
- Retention dry-run/execution parity via one shared selector (ADR-0002); no self-approval on erasure, enforced as ABAC policy data (ADR-0001) — Session 3.
- Audit log: DB grants + hash chain + external anchoring, all three together, none optional (ADR-0003) — Session 3.
- Connector integration is async/webhook-based, never synchronous (ADR-0004) — Session 3.
- **No tenant/org column anywhere in the schema** (ADR-0005) — Session 3.
- **Export bundles are excluded from long-retention backups; audit logs and deletion certificates are not** — a new decision from this session, must be respected explicitly at Session 8, not overridden for backup-implementation simplicity.

**Implementation state:**
- Done: repository skeleton, licence, governance docs, finalised brief, scope/non-goals, deep-phase requirements (Session 2), and now full architecture, data model, validated API contracts, and 5 ADRs (Session 3).
- In progress: nothing mid-flight.
- Not started: threat model, environment/CI setup, and all implementation — no application code exists yet.

**Constraints and non-goals:**
- Max 2 new technologies for this repo (ABAC, ASVS L2 mapping) — at cap.
- Full non-goals list unchanged since Session 1 (`01-scope-and-non-goals.md`) — architecture work this session deliberately avoided reintroducing multi-tenancy or an event bus/message broker (that pattern belongs to R04/R07 elsewhere in the portfolio, not duplicated here).

**Deep SDLC phases for this repo:** Requirements Analysis (complete), Retirement/Handover & Disposal (not yet started)
**Intentionally light phases:** Discovery (concluded), Operations (baseline only)

**Task for this session (single objective):**
Produce the STRIDE threat model and OWASP ASVS L2 mapping, explicitly covering: the connector callback anomaly case, the public demo instance's synthetic-data-only constraint as a named threat category, and the ABAC evaluator itself as a trust boundary.

**Definition of done:**
- Every trust boundary identified in `03-architecture.md`'s diagrams has at least one STRIDE threat, one mitigation, and one owning test reference.
- `docs/security/asvs-mapping.md` exists with concrete control-to-implementation mappings, not just a checklist of unaddressed ASVS items.
- Accepted risks are stated explicitly with revisit triggers, matching the honesty standard already set in ADR-0003.

**Files to attach or paste:**
- `docs/project-memory/03-architecture.md`
- `docs/project-memory/04-data-model.md`
- `docs/project-memory/05-api-contracts.md`
- `docs/adr/` (all 5 files)
- `docs/project-memory/12-session-handoff.md` (this file)

**Ground rules:** Do not change the stack. Do not introduce a third new technology. Do not reopen GDPR-only, single-tenant, public-demo, or any of the 5 ADR decisions — treat them as settled unless the project owner explicitly reopens them. Do not implement anything yet — this is still a design-phase session. Ask before introducing any new dependency or scope item not already anticipated above.
