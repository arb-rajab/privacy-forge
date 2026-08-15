# Requirements
> Purpose: testable statements of what the system must do and how well.
> Project: privacy-forge (public)
> Last updated: 2026-08-10
> Depth: **DEEP** — this is one of the repository's two demonstrated-deeply SDLC phases.

## Roles and permissions matrix

| Role | Can | Cannot |
|---|---|---|
| **Owner** (org admin) | Everything below, plus: manage staff accounts and roles, configure ABAC policies, view full audit log, configure demo/safety settings | Full capability access, subject to the same integrity controls (e.g. separation-of-duties) that apply system-wide — see ADR-0007, which deliberately denies an Owner who verified an identity from also approving that same DSAR's erasure. Role scope is not exempt from cross-field policy conditions.[^owner-abac] |
| **Privacy Manager** | Define consent purposes and lawful bases; publish/version consent notices; define and dry-run retention policies; review and action DSARs (verify identity, approve export/erasure); view RoPA; view audit log entries related to their actions | Manage staff accounts/roles; change ABAC policy definitions; approve their own DSAR erasure without a second reviewer (separation-of-duties rule — not yet assigned an FR ID; to be formalised as an ABAC policy in Session 3, see handoff) |
| **Support Staff** | Triage incoming DSARs (categorise, request more info from the data subject); view DSAR status; view non-sensitive consent records | Approve identity verification; approve or execute erasure; view the audit log; view or edit retention policies or RoPA |
| **Data Subject** (external, unauthenticated except via signed link) | Give/view/withdraw their own consent via the public widget; submit a DSAR; view their own DSAR status via a signed link; download their own export bundle via a signed URL | View any other data subject's data; view staff-side screens; view the audit log; act without a valid signed link or session for anything beyond initial consent capture |
| **Connector** (service account, machine-to-machine) | Receive erasure/export task callbacks via the connector webhook contract; report task completion/failure | Initiate DSARs; read data outside the specific task payload it was issued; access the admin UI |

[^owner-abac]: Corrected 2026-08-15 (Session 10). The prior wording ("Nothing
withheld within the instance") read as exempting Owner from
separation-of-duties; Session 9's NFR-005 matrix confirmed that is not how
ADR-0007's policy actually behaves — an Owner who verified identity on a DSAR
is correctly denied when approving that same DSAR's erasure, by design (the
control would be meaningless if the most-privileged role were exempt from
it). See `docs/project-memory/09-decision-log.md` for the corresponding
decision-log entry. This is a documentation correction only; no ADR was
reopened and no policy behaviour changed.

Every role's actions are logged to the audit trail (FR-014) with the ABAC
policy ID that authorised the action (FR-013) — this is a hard requirement
inherited from Session 0's learning objective, not optional polish.

## User stories with acceptance criteria

### Consent registry

**US-001 — Define a consent purpose and lawful basis**
As a Privacy Manager, I want to define a named consent purpose with an
associated GDPR lawful basis, so that every piece of personal data processing
has a documented justification.
**Acceptance criteria:**
- Given I am a Privacy Manager, when I create a purpose with a name,
  description, and one of the six GDPR Article 6 lawful bases, then the
  purpose is saved and versioned at version 1.
- Given a purpose already has active consent records, when I attempt to
  delete it, then the system refuses and instructs me to deprecate it instead.

**US-002 — Publish a versioned consent notice**
As a Privacy Manager, I want to publish a consent notice tied to a purpose,
so that data subjects see accurate, dated wording when they consent.
**Acceptance criteria:**
- Given a purpose exists, when I publish a notice with body text, then the
  notice is versioned and timestamped, and previous versions remain
  retrievable (never overwritten).
- Given a notice is republished with materially different wording, when
  existing consent records reference the old version, then those records are
  **not** silently upgraded to imply consent to the new wording.

**US-003 — Capture consent via API or embeddable widget**
As a developer integrating privacy-forge, I want an API and an embeddable
widget to capture consent at the point of collection, so that consent is
recorded at the moment it is actually given.
**Acceptance criteria:**
- Given a valid purpose and notice version, when the capture API receives a
  consent event with a subject identifier, timestamp, and notice version,
  then a consent record is created and an audit log entry is written.
- Given the widget is embedded on a third-party page, when a visitor accepts,
  then the resulting API call includes the notice version they were actually
  shown, not just the latest version.
- Given a capture request omits a required field, when submitted, then the
  API returns a 422 with a field-level error, and no partial record is created.

**US-004 — Withdraw consent**
As a data subject, I want to withdraw consent I previously gave, so that
processing based on that consent stops.
**Acceptance criteria:**
- Given I have an active consent record, when I withdraw it via the widget or
  API, then the record is marked withdrawn with a timestamp (not deleted —
  withdrawal is itself evidence) and an audit log entry is written.
- Given consent is withdrawn, when any part of the system checks "is this
  purpose consented to," then the check returns false from the moment of
  withdrawal onward.

### Data-subject requests (DSAR)

**US-005 — Submit a DSAR via the public portal**
As a data subject, I want to submit a request to access, export, or erase my
data, so that I can exercise my GDPR rights without emailing support.
**Acceptance criteria:**
- Given I visit the public DSAR portal, when I submit a request specifying
  the request type (access/export/erasure) and my identifying details, then
  a DSAR record is created in `pending_verification` status and I receive a
  signed status-tracking link.
- Given I submit more than 3 DSARs from the same identifier within 24 hours,
  when I submit a 4th, then the system rate-limits the submission (see
  NFR-006) rather than silently creating unlimited duplicate records.

**US-006 — Verify identity before acting on a DSAR**
As a Privacy Manager, I want an identity-verification gate before any export
or erasure proceeds, so that the system cannot be used to exfiltrate or
destroy someone else's data.
**Acceptance criteria:**
- Given a DSAR in `pending_verification`, when I mark identity as verified
  (via the documented manual-verification stub — see non-goals: no automated
  ID verification in v1), then the DSAR moves to `in_progress` and this
  action is audit-logged.
- Given identity is not yet verified, when any export or erasure task is
  attempted, then the system refuses and logs the refusal.

**US-007 — Orchestrate a DSAR across registered data sources**
As a Privacy Manager, I want the system to dispatch export/erasure tasks to
every registered connector, so that fulfilling a request doesn't require me
to manually chase every system by hand.
**Acceptance criteria:**
- Given N connectors are registered, when a verified DSAR is actioned, then
  N tasks are created, one per connector, each independently tracked.
- Given a connector task fails or times out, when this happens, then the DSAR
  as a whole is marked `partially_complete`, not silently marked complete,
  and the failure is visible to the Privacy Manager.

**US-008 — Receive an export bundle**
As a data subject, I want to receive my data as a downloadable bundle, so
that I have a portable copy of what is held about me.
**Acceptance criteria:**
- Given all connector export tasks succeed, when the bundle is assembled,
  then it is produced in both JSON and CSV, encrypted at rest, and made
  available via a signed URL with a TTL of no more than 72 hours.
- Given the signed URL has expired, when it is accessed, then access is
  refused and a new request must be made — the link is never valid
  indefinitely.

**US-009 — Erasure with a verification receipt**
As a data subject, I want confirmation that my data was actually erased, not
just a "we'll get to it" message, so that I have evidence of compliance.
**Acceptance criteria:**
- Given all connector erasure tasks succeed, when erasure completes, then a
  deletion certificate is generated listing which systems confirmed deletion
  and when, and the data subject receives it.
- Given a connector cannot confirm erasure, when this occurs, then the
  certificate explicitly states the exception rather than claiming full
  erasure — the system must never overstate what it achieved.

### Retention

**US-010 — Define a retention policy per data category**
As a Privacy Manager, I want to define how long each data category is kept,
so that data isn't retained indefinitely by default.
**Acceptance criteria:**
- Given a data category exists, when I define a retention period and a
  post-expiry action (erase/anonymise), then the policy is saved and
  versioned.

**US-011 — Dry-run a retention policy before it executes**
As a Privacy Manager, I want to preview what a retention policy would delete
before it runs for real, so that I don't destroy data by mistake.
**Acceptance criteria:**
- Given a policy is defined, when I request a dry run, then the system
  reports exactly which records would be affected, with no side effects.
- Given the dry-run report, when I compare it against a subsequent real run
  under unchanged data, then the real run affects exactly the same records
  (same count, same identifiers) — dry-run and real execution must use
  identical selection logic.

**US-012 — Scheduled retention execution with a certificate**
As a Privacy Manager, I want retention policies to run automatically and
produce evidence, so that deletion is routine rather than manual and forgotten.
**Acceptance criteria:**
- Given a policy's schedule is due, when the scheduler runs, then affected
  records are processed per the policy's action and a deletion certificate is
  generated and stored (not just logged transiently).

### RoPA and audit

**US-013 — Export a Record of Processing Activities**
As a Privacy Manager, I want to export a RoPA document, so that I can respond
to an audit or due-diligence request without assembling one from scratch.
**Acceptance criteria:**
- Given purposes, lawful bases, and retention policies are defined, when I
  request a RoPA export, then it is generated covering all active purposes
  with their lawful basis, retention period, and categories of data subjects
  and data involved, in a format suitable for external sharing (PDF or CSV).

**US-014 — Tamper-evident audit logging**
As an Owner, I want every sensitive action logged in a way that cannot be
quietly altered after the fact, so that the audit trail itself is trustworthy
evidence.
**Acceptance criteria:**
- Given any consent, DSAR, retention, or authorisation-relevant action
  occurs, when it happens, then an audit log entry is written including the
  ABAC policy ID that authorised it, and the entry is included in the hash
  chain.
- Given an audit log entry is modified directly at the database level
  (simulating tampering), when the chain is verified, then verification
  fails and identifies the point of tampering.

### Authorisation (ABAC)

**US-015 — Policy-based authorisation on every sensitive action**
As an Owner, I want access decisions to be evaluated against explicit
attribute-based policies rather than hardcoded role checks, so that
authorisation logic is auditable and independently testable.
**Acceptance criteria:**
- Given a user attempts a sensitive action (defined as: DSAR verification,
  export approval, erasure approval, retention policy execution, audit log
  access), when the action is attempted, then an ABAC policy evaluates
  subject attributes (role), resource attributes (data category,
  sensitivity), and environment attributes (e.g. IP allowlist if
  configured), and the decision (including the policy ID) is logged
  regardless of allow or deny.
- Given every (role × sensitive-action) pair, when tested exhaustively, then
  the observed allow/deny outcome matches the documented permissions matrix
  above with zero discrepancies (this is the authorisation test suite
  referenced in `07-testing-strategy.md`, to be built at Session 7).

## Functional requirements

| ID | Requirement | Priority | Verified by |
|---|---|---|---|
| FR-001 | System supports defining consent purposes with a GDPR Art. 6 lawful basis | Must | US-001, feature test |
| FR-002 | Consent notices are versioned; historical versions are immutable and retrievable | Must | US-002, feature test |
| FR-003 | Consent capture available via REST API and an embeddable widget | Must | US-003, feature + widget e2e test |
| FR-004 | Consent withdrawal is recorded, not deleted, and takes effect immediately | Must | US-004, feature test |
| FR-005 | DSAR submission available via a public portal without requiring an account | Must | US-005, feature test |
| FR-006 | DSAR submission is rate-limited per identifier | Must | US-005, feature test |
| FR-007 | No export or erasure task executes before identity verification is recorded | Must | US-006, feature + authorisation test |
| FR-008 | DSAR orchestration dispatches independently tracked tasks to all registered connectors | Must | US-007, integration test |
| FR-009 | Partial connector failure results in `partially_complete`, never a false `complete` | Must | US-007, integration test |
| FR-010 | Export bundles are provided in JSON and CSV via a time-limited signed URL (≤72h TTL) | Must | US-008, feature test |
| FR-011 | Erasure produces a deletion certificate; incomplete erasure is stated explicitly, never overstated | Must | US-009, feature test |
| FR-012 | Retention policies support a no-side-effect dry run whose selection logic is identical to real execution | Must | US-011, property/parity test |
| FR-013 | Every sensitive action is authorised via ABAC policy evaluation and logged with the deciding policy ID | Must | US-015, authorisation test suite (Session 7) |
| FR-014 | Audit log entries are hash-chained; tampering is detectable via chain verification | Must | US-014, dedicated tamper test |
| FR-015 | Retention execution is schedulable and produces a stored deletion certificate | Must | US-012, feature test |
| FR-016 | RoPA can be exported covering all active purposes, lawful bases, and retention periods | Must | US-013, feature test |
| FR-017 | System supports exactly one organisation per instance (no tenant model) | Must | Architecture review, Session 3 |
| FR-018 | Demo instance seed data is synthetic; no code path exists for importing real personal data into the demo | Must | Manual pre-launch checklist, Session 8 |
| FR-019 | Connector integration uses a documented, signed-webhook contract; no specific third-party connector ships in v1 | Should | US-007, contract test against a reference stub connector |
| FR-020 | Manual identity-verification stub only — no automated ID-verification provider integration in v1 | Won't (this release) | Recorded as non-goal; revisit trigger in `01-scope-and-non-goals.md` |

## Non-functional requirements (numeric targets)

| ID | Category | Requirement | Target | Verified by |
|---|---|---|---|---|
| NFR-001 | Usability | Time for a new operator to self-host and complete a full consent→DSAR→export cycle from the README alone | ≤ 15 minutes | Manual timed test, Session 9 |
| NFR-002 | Performance | DSAR export bundle generation time for a data subject with ≤10,000 records across all connectors | ≤ 2 minutes p95 | Load test, Session 7 |
| NFR-003 | Performance | Consent capture API response time | ≤ 200ms p95 under 50 req/s | Load test, Session 7 |
| NFR-004 | Security | Critical/high findings from CodeQL and `osv-scanner` at v1.0.0 tag | 0 | CI gate, every PR from Session 5 onward |
| NFR-005 | Security | Authorisation test coverage across (role × sensitive action) pairs | 100%, 0 discrepancies | Authorisation test suite, Session 7 |
| NFR-006 | Security | DSAR submission rate limit per identifier | ≤ 3 per 24h (configurable) | Feature test, Session 6b |
| NFR-007 | Reliability | Signed export/status URL maximum time-to-live | ≤ 72 hours | Feature test, Session 6b |
| NFR-008 | Auditability | Sensitive actions logged with policy ID | 100% — 0 unlogged sensitive actions | Authorisation test suite, Session 7 |
| NFR-009 | Integrity | Audit log tamper-detection | Detects 100% of direct-database modifications in the test corpus | Dedicated tamper test, Session 7 |
| NFR-010 | Data safety | Real PII present in the public demo instance | 0, verified at every deploy | Manual pre-launch checklist (recorded as a launch blocker), Session 8 |
| NFR-011 | Availability (demo) | Public demo instance uptime | Best-effort — no formal SLA; stated explicitly in the demo's own README banner | N/A — this is a portfolio demo, not a production SLA commitment |

## Data classification

| Data element | Classification | Retention | Encryption | Lawful basis (for the tool itself, re: subject data it processes) |
|---|---|---|---|---|
| Consent record (subject identifier, purpose, notice version, timestamp) | Personal data | Per configured retention policy; never auto-deleted while a related lawful-basis question is open | At rest (DB-level column encryption for the identifier) | Legitimate interest — necessary to demonstrate lawful processing (Art. 6(1)(f)) |
| DSAR record (identity claim, request type, status) | Personal data, elevated sensitivity during identity verification | Retained per statutory limitation period after closure, then erased | At rest; identity-claim fields encrypted | Legal obligation — fulfilling a GDPR Art. 15/17/20 request |
| Export bundle (assembled subject data) | Personal data, high sensitivity | Deleted from storage after signed-URL TTL expiry, regardless of download status | At rest and via signed URL (TLS) | Legal obligation |
| Deletion certificate | Evidentiary record, low re-identification risk (contains confirmation metadata, not the erased data itself) | Retained indefinitely as compliance evidence | At rest | Legal obligation |
| Audit log entry | Evidentiary record; may reference subject identifiers | Retained indefinitely (this is the point of an audit trail) | At rest, hash-chained for integrity (not confidentiality) | Legal obligation / legitimate interest |
| RoPA record | Organisational metadata, not personal data | Retained while the purpose is active + a defined grace period | At rest | N/A — not personal data |
| Staff account (Owner/Privacy Manager/Support Staff) | Personal data (employee) | Retained for employment duration + access-review period | At rest, password hashed | Legitimate interest / contract |
| Demo seed data | Synthetic — **not personal data at all** | N/A — regenerated per deploy | N/A | N/A |

## Integration requirements

- **Connector contract:** a documented HTTP webhook interface for export and
  erasure tasks: signed requests (HMAC + timestamp), idempotent task IDs,
  explicit success/failure/partial-failure response states. No specific
  third-party connector (CRM, marketing tool, etc.) ships in v1 — a single
  reference/stub connector is built to prove the contract, per non-goal
  table entry "Real integrations with production data sources."
- **No inbound integrations required for v1** beyond the connector contract
  itself and the public consent-capture API/widget.

## Constraints

- Must run as a single-organisation, self-hosted instance (FR-017) — no
  multi-tenancy.
- Must scope regulatory logic to GDPR/UK-GDPR only — no CCPA rule paths, per
  Session 1 decision.
- Must not present any automated legal-basis recommendation or AI-generated
  compliance advice (non-goal, Session 1).
- Public demo instance must never contain real personal data at any point
  (FR-018, NFR-010) — this constrains seed-data generation design (Session 3)
  and the deployment checklist (Session 8) simultaneously.
- Stack is fixed: Laravel 11, Vue 3/Inertia, PostgreSQL, Redis, S3-compatible
  storage — per the frozen ledger allocation (Session 0).

## GDPR Article Requirements Traceability Matrix

This is the load-bearing artifact for this repo's deep Requirements Analysis
phase — every MVP feature must trace to a specific article, and every
article in scope must trace to at least one FR and one planned test.

| GDPR Article | Right/obligation | Traced FR(s) | Traced test (planned location) |
|---|---|---|---|
| Art. 6 — Lawfulness of processing | Processing must have a documented lawful basis | FR-001 | `tests/Feature/ConsentPurposeTest.php` |
| Art. 7 — Conditions for consent | Consent must be freely given, specific, informed, and withdrawable as easily as given | FR-002, FR-003, FR-004 | `tests/Feature/ConsentCaptureTest.php`, `tests/Feature/ConsentWithdrawalTest.php` |
| Art. 12 — Transparent information | Communications to data subjects must be concise, transparent, and accessible | FR-005, FR-010, FR-011 | `tests/Feature/DsarPortalTest.php` (copy review, not just functional assertion) |
| Art. 13/14 — Information to be provided | Consent notices must state purpose and basis | FR-002 | `tests/Feature/ConsentNoticeContentTest.php` |
| Art. 15 — Right of access | Data subject can obtain confirmation and a copy of their data | FR-005, FR-007, FR-010 | `tests/Feature/DsarAccessRequestTest.php` |
| Art. 17 — Right to erasure | Data subject can request erasure; controller must confirm | FR-005, FR-007, FR-009, FR-011 | `tests/Feature/DsarErasureTest.php` |
| Art. 20 — Right to data portability | Data provided in a structured, commonly used, machine-readable format | FR-010 (JSON + CSV) | `tests/Feature/ExportFormatTest.php` |
| Art. 30 — Records of processing activities | Controller must maintain a RoPA | FR-016 | `tests/Feature/RopaExportTest.php` |
| Art. 5(1)(e) — Storage limitation | Personal data kept no longer than necessary | FR-012, FR-015 | `tests/Feature/RetentionExecutionTest.php`, `tests/Feature/RetentionDryRunParityTest.php` |
| Art. 5(2) / Art. 24 — Accountability | Controller must demonstrate compliance | FR-013, FR-014, FR-016 | Authorisation test suite + tamper test (Session 7) |

**RTM completeness check:** every FR-001–FR-018 above traces to at least one
article row (FR-019/FR-020 are integration/non-goal items and are
deliberately excluded from the RTM — they are not rights-fulfilment
features). Zero MVP features are untraceable; zero listed articles lack a
mapped FR and a named (if not yet written) test.
