# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Session 4: STRIDE threat model covering 5 trust boundaries and 20 threats,
  including a dedicated Demo Instance Data Safety section (scheduled resets,
  no persistent shared admin credential, connector registration compiled
  out entirely on the demo build) and 4 explicitly accepted risks with
  revisit triggers.
- Session 4: OWASP ASVS L2 control mapping
  (`docs/security/asvs-mapping.md`), with an explicit caveat that exact
  clause numbers require verification against the current standard before
  Session 6 implementation.
- Session 4: ADR-0006 — the ABAC evaluator fails closed by default on any
  error; `policy.update` added to the sensitive-action registry as an
  Owner-only, audit-logged action.
- Session 4: resolved the Session 3 connector-callback anomaly — a
  conflicting terminal-status callback now automatically disables the
  connector pending manual review.
- Session 3: full architecture (system context, container, and 3 sequence
  diagrams), data model (14-entity ERD with invariants and their exact
  enforcement mechanism), and API contracts, including a hand-authored,
  machine-validated OpenAPI 3.1 specification
  (`docs/architecture/openapi.yaml`).
- Session 3: 5 ADRs — ABAC policy model with separation-of-duties as policy
  data; retention dry-run/execution parity via a shared selector; audit-log
  tamper-evidence via hash chain + DB grants + external anchoring; async
  connector webhook contract; single-organisation data model with no
  tenant column.
- Session 3: identified and resolved a backup/retention-TTL conflict —
  export bundles are excluded from long-retention backups so the 72-hour
  signed-URL promise holds in practice, not just in the application layer.
- Session 2: full requirements document (deep phase) — roles/permissions
  matrix, 15 user stories with acceptance criteria, 20 functional
  requirements, 11 numeric NFRs, data classification for all 8 data
  elements, connector integration requirements, and the GDPR Article
  Requirements Traceability Matrix (Arts. 5, 6, 7, 12, 13/14, 15, 17, 20, 24,
  30 — each mapped to specific FRs and named test locations).
- Session 1: finalised project brief with validated business assumptions
  (GDPR/UK-GDPR only, single-tenant, public hosted demo with a mandatory
  synthetic-data safety constraint).
- Session 1: scope and non-goals document, including an 8-item non-goals
  table with reconsider-triggers and a formal "definition of v1 complete."
- Repository governance: framework allocation ledger row confirmed
  (`UNIQUE` — Vue 3 + Laravel 11, no flagship collision).
- Project Memory Pack scaffolded (15-file structure under
  `docs/project-memory/`).
- Session 0 deliverables: ledger confirmation, project brief stub,
  repository skeleton, licence, contribution/security/conduct policies.

Nothing has shipped yet — this project is pre-v0.1.0.
