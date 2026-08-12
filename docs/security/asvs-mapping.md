# OWASP ASVS L2 Control Mapping
> Purpose: demonstrate how privacy-forge's design satisfies ASVS Level 2
> controls — this is one of the repository's two stated learning objectives
> (Session 0 ledger).
> Project: privacy-forge (public)
> Last updated: 2026-08-12

**A note on precision.** This mapping is organised by ASVS chapter/category,
which are stable points of reference. I have deliberately **not** cited
specific numbered sub-requirements (e.g. "V4.1.3") from memory, because I
cannot fully verify exact clause numbers and wording against the current
ASVS version without a live reference, and asserting false precision would
undermine the exact credibility this document exists to build. **Before
Session 6 implementation, pin an exact ASVS version and re-derive precise
clause references from the primary source** (https://owasp.org/www-project-application-security-verification-standard/)
rather than trusting numbers written here from memory. This limitation is
itself recorded as a known gap, not hidden.

| ASVS Chapter | Representative L2 control | How privacy-forge satisfies it | Implementation reference |
|---|---|---|---|
| **V1 — Architecture, Design & Threat Modeling** | A documented threat model exists and is kept current | This document, `06-security-threat-model.md`, and 6 ADRs in `docs/adr/` | Session 3, Session 4 |
| **V2 — Authentication** | Credentials are stored using an appropriate one-way hash; login attempts are rate-limited | Laravel's default password hashing (bcrypt/argon2); login rate-limiting per T-13 | `03-architecture.md`, T-13 mitigation |
| **V3 — Session Management** | Session tokens are invalidated on logout and on privilege-relevant changes; cookies use `Secure`/`HttpOnly`/`SameSite` | T-11 mitigation | `06-security-threat-model.md`, T-11 |
| **V4 — Access Control** | Access control decisions are enforced server-side, deny-by-default, and cannot be bypassed by direct object reference | ABAC evaluator (ADR-0001), fail-closed by design (ADR-0006), signed-token-only access for data subjects (T-05) rather than plain object IDs | ADR-0001, ADR-0006, T-05, T-14 |
| **V5 — Validation, Sanitization & Encoding** | All input is validated server-side; output is encoded appropriately for its context | FR-003's 422-on-invalid-input requirement; Laravel's request validation layer; Vue's default output escaping in the admin SPA | `02-requirements.md` FR-003, Session 6 implementation |
| **V6 — Stored Cryptography** | Sensitive data is encrypted at rest with an appropriate algorithm and key management | Column-level encryption on identifier fields (`04-data-model.md`); secrets via managed store, not in code | `04-data-model.md`, Secrets management section above |
| **V7 — Error Handling & Logging** | Errors do not leak sensitive information; security-relevant events are logged | RFC 9457 `ProblemDetail` responses deliberately omit internals beyond a `policy_id`; audit log covers every sensitive action (FR-013/014) | `05-api-contracts.md` Error model, ADR-0003 |
| **V8 — Data Protection** | Sensitive data is identified and classified; retention is limited and enforced | Full data classification table (`02-requirements.md`); retention engine with dry-run/execution parity (ADR-0002) | `02-requirements.md`, ADR-0002 |
| **V9 — Communications** | TLS is enforced for all communications carrying sensitive data | Signed URLs and API endpoints served over TLS only; HSTS (finalised at Session 5 environment setup) | Session 5 (to be implemented) |
| **V10 — Malicious Code** | Dependencies are scanned for known vulnerabilities and malicious packages | CodeQL + `osv-scanner` on every PR (NFR-004); SBOM at release | Dependency and supply-chain controls, above |
| **V11 — Business Logic** | Business logic cannot be bypassed or abused via unexpected sequencing (e.g. skipping verification before erasure) | FR-007 (no export/erasure before verification); separation-of-duties policy (ADR-0001); anomaly handling for conflicting connector callbacks (T-09) | ADR-0001, T-04, T-09 |
| **V12 — Files & Resources** | Uploaded/generated files are handled safely; download links are time-limited and access-controlled | Export bundles: signed URLs, ≤72h TTL, deleted on expiry regardless of download (NFR-007) | `04-data-model.md` Retention and deletion rules |
| **V13 — API & Web Service** | APIs enforce the same access controls as the rest of the application; rate limiting is applied | ABAC applies identically whether an action originates from the admin SPA or a direct API call — there is no "trusted internal caller" bypass | ADR-0001, `05-api-contracts.md` |
| **V14 — Configuration** | Secure configuration is the default; debug features are disabled in production; secrets are not in source control | `.env.example` only (Session 0 `.gitignore`); demo-specific configuration disables connector registration entirely (T-18) rather than hiding it in the UI | Session 0, T-18 |

## Known gaps at this stage

- No implementation exists yet (Session 4 is a design-phase session) — every
  "how satisfied" column above describes a design commitment, not a verified
  running control. Verification happens progressively from Session 5
  onward, and the Gate 7→8 checklist in the session system requires this
  mapping to be re-checked against actual behaviour before Session 8's
  release-readiness gate, not just at design time.
- Exact ASVS clause numbers are deliberately not cited from memory (see the
  note at the top of this document) — resolving that is a Session 6 task,
  not deferred indefinitely.
