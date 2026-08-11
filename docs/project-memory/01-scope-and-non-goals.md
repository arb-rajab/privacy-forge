# Scope and Non-Goals
> Purpose: prevent scope creep by writing down what this will never do.
> Project: privacy-forge (public)
> Last updated: 2026-08-10

## MVP boundary (in scope)

- [ ] Consent registry: purposes, lawful bases, versioned consent notices,
      capture API + embeddable widget, withdrawal.
- [ ] Data-subject requests (DSAR): intake portal, identity-verification
      stub, task orchestration across registered "data source" connectors,
      export bundle (JSON + CSV) delivered via a short-TTL signed URL,
      erasure with a verification receipt.
- [ ] Retention policies: per-data-category rules, dry-run preview,
      scheduled execution, deletion certificates.
- [ ] Records of Processing Activities (RoPA) register with export.
- [ ] Tamper-evident audit log (hash chain, periodic anchor).
- [ ] ABAC authorisation across all of the above, with every decision logged
      against the policy ID that produced it.
- [ ] Single organisation per instance (no multi-tenancy).
- [ ] GDPR/UK-GDPR regulatory frame only.
- [ ] A public demo instance running on synthetic seed data, in isolated
      infrastructure, with a spend cap.

This list is the literal checklist for "MVP complete" — see the Definition
below.

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
