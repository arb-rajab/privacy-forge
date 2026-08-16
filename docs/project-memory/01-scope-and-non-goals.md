# Scope and Non-Goals
> Purpose: prevent scope creep by writing down what this will never do.
> Project: privacy-forge (public)
> Last updated: 2026-08-17

## MVP boundary (in scope)

**Checked item-by-item against the actual codebase, Session 12
(2026-08-17)** — not re-asserted from memory. Backend/API + test coverage
is complete for five of nine items; the remaining four have a real,
specific gap named inline rather than left as a vague "mostly done".

- [ ] Consent registry: purposes, lawful bases, versioned consent notices,
      capture API + embeddable widget, withdrawal. **Backend complete**
      (US-001–004: purposes, lawful bases, versioned notices, capture API,
      withdrawal all implemented and tested). **Not done: the embeddable
      widget itself.** `resources/js/` contains only the default Inertia
      scaffold (`Pages/Welcome.vue`) — no widget component exists anywhere
      in the repository as of this session.
- [ ] Data-subject requests (DSAR): intake portal, identity-verification
      stub, task orchestration across registered "data source" connectors,
      export bundle (JSON + CSV) delivered via a short-TTL signed URL,
      erasure with a verification receipt. **Backend complete** (US-005–009:
      submission API, identity-verification stub, connector task
      orchestration, export bundle assembly + signed download, erasure +
      deletion certificate all implemented and tested). **Not done: the
      public-facing intake portal itself** — `POST /dsar` and the signed
      status/download endpoints exist and are fully tested at the API
      level, but there is no actual page a data subject visits (same gap
      as the consent widget above: no frontend beyond the Inertia
      scaffold).
- [x] Retention policies: per-data-category rules, dry-run preview,
      scheduled execution, deletion certificates. Complete (Session 11:
      US-010/011/012; Session 12 fixed a real re-selection bug in
      `RetentionSelector` — see `09-decision-log.md`). This item's own
      wording never promised a UI, only the policy/preview/execution/
      certificate mechanism, which is real and tested end-to-end against
      live `consent_records`/`dsar_requests` data.
- [x] Records of Processing Activities (RoPA) register with export.
      Complete (Session 12: US-013/FR-016 — `ropa.export`, CSV + PDF,
      generated on demand from live purpose/category/policy data, gated
      and tested). Same scoping note as retention: this item's wording
      asks for "register with export," not a dashboard — v1 deliberately
      ships an export, not a visualisation UI (see "Deferred to backlog"
      below).
- [ ] Tamper-evident audit log (hash chain, periodic anchor). **Hash chain
      complete** (ADR-0003, Session 7-ish — entries are hash-chained and
      tamper detection is tested). **Not done: the periodic external
      anchor.** `routes/console.php`'s own comment states this plainly
      ("The audit-log anchor... remain[s] unbuilt"); confirmed this
      session — no anchoring job/command exists anywhere in `app/Console`
      or `app/Services`. Entry-level tamper detection is real; the
      stronger guarantee ADR-0003 describes (protection against a
      sufficiently privileged attacker who edits entries *and*
      recomputes the chain) is not yet in place.
- [x] ABAC authorisation across all of the above, with every decision logged
      against the policy ID that produced it. Complete — five sensitive
      actions registered and exhaustively tested (`dsar.identity.verify`,
      `dsar.erasure.approve`, `policy.update`, `retention.policy.manage`,
      `ropa.export`), NFR-005's 25-cell matrix passes with zero
      discrepancies.
- [x] Single organisation per instance (no multi-tenancy). Complete
      (ADR-0005 — no tenant column anywhere in the schema).
- [x] GDPR/UK-GDPR regulatory frame only. Complete — no CCPA (or any other
      jurisdiction's) rule path exists anywhere in the codebase.
- [ ] A public demo instance running on synthetic seed data, in isolated
      infrastructure, with a spend cap. **Not done.** Confirmed this
      session: there is no `database/seeders/` directory at all in the
      repository, and `docs/project-memory/08-deployment-and-operations.md`
      is an entirely unwritten stub (every section header, including
      "Backup and restore" and "Capacity and cost notes," has no content
      under it) — despite `03-architecture.md` stating restore drills were
      "recorded in `08-deployment-and-operations.md` (Session 8)." That
      specific cross-reference does not hold; flagged here rather than
      silently left for a future session to discover the same way the
      Session 8 TTL-testing claim was checked and clarified at Session 11.

This list is the literal checklist for "MVP complete" — see the Definition
below. **As of Session 12: 5 of 9 items are genuinely complete; the other
4 share one root cause (no frontend beyond the default Inertia scaffold —
consent widget, DSAR portal) plus two independent, narrower gaps (the
audit-log anchor job; the demo instance/seeders). The project cannot yet
be credibly called MVP-complete per its own Definition below, specifically
condition 1 ("every box... checked and demonstrably working end-to-end").**

## Explicit non-goals

| Non-goal | Why excluded | Would reconsider if |
|---|---|---|
| Cookie-banner / marketing CMP | Different problem (tracking-consent-on-a-website vs. lawful-basis-and-DSAR-lifecycle); would dilute the requirements-rigour focus | A future portfolio direction specifically targets marketing-consent tooling |
| Legal advice or jurisdiction-specific policy templates | Requires legal qualification this developer doesn't hold; liability-bearing content has no place in a portfolio artifact | Never, for this repo — a legal partner co-authoring templates could justify it in a *different*, clearly-labelled project |
| Multi-jurisdiction rule packs beyond GDPR/UK-GDPR (incl. CCPA) | Roughly doubles Requirements Analysis scope for a "broad but shallow" result, which works against this repo's deep-phase goal | If a later private-track engagement (e.g. a US-market client) specifically needs it — handled as a private fork, not scope creep here |
| Multi-tenancy / SaaS hosting model | Distinct engineering problem (tenant isolation, cross-tenant leakage testing); deliberately reserved for private-track direction PR02 | If this repo is ever repositioned as a hosted product rather than a portfolio reference — not currently planned |
| Enterprise SSO / SCIM | Adds an integration surface with no proportional evidence value for a single-org self-hosted tool; the roles/permissions story is better demonstrated through ABAC than through IdP integration | If a specific private-track client engagement requires it |
| DPIA (Data Protection Impact Assessment) workflow automation | A distinct, deep compliance workflow in its own right — would double the requirements surface without adding new *architectural* evidence | If DPIA support becomes the flagship's own v2 milestone, scoped and estimated on its own terms |
| Automated legal-basis recommendation / "AI compliance assistant" | Would misrepresent the tool's authority — recommending a lawful basis is a legal judgement, not a software feature this project should imply it can make | Not planned; conflicts with the project's credibility goal rather than supporting it |
| Real integrations with production data sources (CRMs, marketing tools, etc.) | The DSAR orchestration *connector interface* is in scope; specific third-party connectors are not — they're integration work with no architectural novelty | If a private-track client needs a specific connector, build it privately against the public connector contract |

## Deferred to backlog (in scope eventually, not for v1)

- Additional DSAR connector examples beyond the reference implementation.
- A richer admin dashboard for RoPA visualisation (v1 ships an export, not a
  dashboard).
- CCPA support as an optional module, gated behind its own requirements pass.
- Localisation of the data-subject-facing portal.

See `11-backlog.md` (populated from Session 6 onward) for the live version of
this list.

## Definition of "v1 complete"

v1.0.0 may be tagged only when:
1. Every box in the MVP boundary checklist above is checked and demonstrably
   working end-to-end (not merely coded — verified per the acceptance
   criteria in `02-requirements.md`).
2. All five success metrics in `00-project-brief.md` are met and verifiable
   by a third party, not just asserted.
3. The Gate 9→10 checklist in the session system (`04-session-system-and-
   templates.md`) passes: README quickstart verified on a clean machine,
   diagrams current, demo available, SDLC evidence map complete, case study
   published.
4. No item in the non-goals table above has silently crept back into scope.
   (This check is deliberately manual, at tag time, precisely because scope
   creep is incremental and easy to miss commit-by-commit.)
