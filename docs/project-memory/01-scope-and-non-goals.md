# Scope and Non-Goals
> Purpose: prevent scope creep by writing down what this will never do.
> Project: privacy-forge (public)
> Last updated: 2026-08-18

## MVP boundary (in scope)

**Checked item-by-item against the actual codebase, Session 13
(2026-08-18)** — not re-asserted from memory. **8 of 9 items are now
genuinely complete as of Session 17** (the audit-log anchor closed this
session, R-04); the remaining item (the demo instance/seeders) is an
independent, narrower gap rather than the single shared "no frontend"
root cause Session 12 found.

- [x] Consent registry: purposes, lawful bases, versioned consent notices,
      capture API + embeddable widget, withdrawal. **Complete.** US-001–004
      backend unchanged; the embeddable widget (`resources/js/widget/`,
      built standalone to `public/widget.js` via `vite.widget.config.js`)
      is new this session and proven genuinely embeddable on a third-party
      page — `public/embed-example.html` is a plain static HTML file with
      no Blade/Inertia involvement, driven by a real headless browser in
      `tests/Browser/DsarLifecycleTest.php`. Withdrawal is part of the
      widget (immediate, same-page-view only — see `09-decision-log.md`
      for why it deliberately does not persist consent state client-side).
- [x] Data-subject requests (DSAR): intake portal, identity-verification
      stub, task orchestration across registered "data source" connectors,
      export bundle (JSON + CSV) delivered via a short-TTL signed URL,
      erasure with a verification receipt. **Complete.** US-005–009 backend
      unchanged; the public intake portal (`/dsar`, `/dsar/status/
      {signedToken}`, `resources/js/Pages/DsarSubmit.vue`/`DsarStatus.vue`)
      was added Session 13, calling the unchanged `POST /dsar`/`GET /dsar/
      status/{signedToken}` contracts. Identity verification and erasure
      approval now also have real staff-facing UI (this session — the
      Admin Dashboard: `/admin/dsar`, `resources/js/Pages/
      AdminDsarQueue.vue`), closing the gap the previous two sessions'
      handoffs both flagged. The whole DSAR-portal-plus-admin-action loop
      is real and browser-tested end to end, including a completed erasure
      with its deletion certificate surfaced back to the data subject, and
      a genuine ADR-0007 separation-of-duties denial rendered in the UI
      when the same admin who verified identity tries to approve.
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
- [x] Tamper-evident audit log (hash chain, periodic anchor). **Complete
      as of Session 17 (R-04).** Hash chain was already complete
      (ADR-0003, Session 7-ish). The periodic external anchor
      (`AuditLogger::anchorChain()`/`verifyAnchors()`, scheduled hourly via
      `audit:anchor-chain`, `routes/console.php`) is now built and proven
      with a real full-chain-rewrite attack simulation
      (`tests/Feature/AuditChainAnchorTest.php`) — `verifyChain()` alone is
      fooled by a DB-level attacker who edits an entry and recomputes every
      subsequent hash; `verifyAnchors()` catches it, because the anchor was
      written to external (`s3` disk) storage before the rewrite happened.
      See `10-risk-register.md`'s R-04 closure entry for full detail.
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
- [x] A public demo instance running on synthetic seed data, in isolated
      infrastructure, with a spend cap. **Revised and closed, Session 24
      — by explicit descoping, not by quietly redefining the checklist.**
      `09-decision-log.md`'s Session 24 entry records the decision
      plainly: this portfolio build descopes actually paying for and
      exposing real public infrastructure (no funded product exists here
      to justify ongoing cloud spend), and instead proves the same
      deployment automation end-to-end against placeholder infrastructure
      values — a fake domain (`demo.privacy-forge.example`,
      RFC 2606-reserved), self-signed ("`tls internal`") TLS in place of
      real ACME issuance, run locally. What this item's original wording
      actually asked for — synthetic seed data (true: `demo:reset`
      re-seeds a fixed minimal baseline), a working demo-safety posture
      (true: all four applicable Demo Instance Data Safety controls
      verified working against this local deployment,
      `06-security-threat-model.md`), and infrastructure isolation/spend
      cap (explicitly marked not-applicable, not silently skipped — there
      is deliberately no real infrastructure to isolate or cap) — is
      honestly satisfied under this revised, descoped scope. What is
      **not** true, stated plainly: there is no live, publicly-reachable
      URL, and per this decision there is not going to be one from this
      portfolio build. See `08-deployment-and-operations.md`'s Sessions
      A/B/C account and `12-session-handoff.md` for the full verification
      record. (Previously: the seeder half closed at Session 16 via R-02;
      the image half — `B-06` — closed at Session 23; this entry
      supersedes the "still not done" wording both of those sessions left
      here.)

This list is the literal checklist for "MVP complete" — see the Definition
below. **As of Session 24: all 9 items are genuinely complete** — the
"no frontend" root cause Session 12 found remains closed, the audit-log
anchor (R-04) closed at Session 17, and the public-demo-instance item
closed this session by the explicit descoping decision above, not by
quietly relaxing its wording. **This does not by itself mean v1.0.0 can be
tagged — see the Definition of "v1 complete" below; conditions 2 and 3 are
independent of this checklist and are not confirmed as of Session 24. See
`12-session-handoff.md`'s Session 24 account for the assessment against
all four conditions. **Correction to that account:** Session 24 flagged
condition 3's checklist citation, `04-session-system-and-templates.md`, as
a documentation gap — that citation was actually just wrong, not missing:
it named a portfolio-level cross-repo planning document that was never
meant to be copied into this repository, not one of this repo's own
Project Memory Pack files (those are numbered `00`–`14`; `04` in that set
is `04-data-model.md`, unrelated). The citation is fixed above. Condition
3 itself was independently re-checked on its real substance (see
`12-session-handoff.md`): the README quickstart genuinely works end to
end on a freshly rebuilt stack (verified, not merely asserted), but the
architecture diagrams in `03-architecture.md` are stale (missing
ADR-0006/0007/0008), no demo asset exists, and no case study has been
published — condition 3 is honestly unmet on those merits, independent of
the now-corrected citation.

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
3. The Gate 9→10 checklist — defined at the portfolio level, in the
   cross-repo session-system document, not as a file inside this repository
   — passes: README quickstart verified on a clean machine, diagrams
   current, demo available, SDLC evidence map complete, case study
   published. (Previously this item cited an in-repo file,
   `04-session-system-and-templates.md`, as the checklist's home — that
   citation was wrong: it named a portfolio-level planning document never
   intended to live in this repository's git history, not one of this
   repo's own numbered Project Memory Pack files. Corrected; see
   `12-session-handoff.md` for the account of finding and fixing it.)
4. No item in the non-goals table above has silently crept back into scope.
   (This check is deliberately manual, at tag time, precisely because scope
   creep is incremental and easy to miss commit-by-commit.)
