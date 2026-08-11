# ADR-0001 — ABAC Policy Model for Sensitive Actions

- **Date:** 2026-08-11
- **Status:** accepted

## Context

FR-013 requires every sensitive action to be authorised via attribute-based
access control (not hardcoded role checks) and logged with the ID of the
policy that made the decision. The Session 2 roles matrix also introduced a
separation-of-duties rule: a Privacy Manager must not be able to approve
their own DSAR erasure without a second reviewer. Both requirements need a
single, testable mechanism — not scattered `if ($user->role === ...)` checks
across controllers, which would make the NFR-005 exhaustive
(role × sensitive-action) test impossible to write with confidence.

## Options considered

**A — Framework-native role gates (Laravel Policies/Gates per role).**
Simple, idiomatic, fast to write. Rejected because role checks alone can't
naturally express resource attributes (e.g. "this data category is high
sensitivity") or environment attributes (e.g. an IP allowlist), and — more
importantly — a gate returning `true`/`false` gives no policy ID to log,
which directly fails FR-013's audit requirement.

**B — Adopt an off-the-shelf ABAC/policy engine or library.** Rejected for
two reasons: first, no PHP-ecosystem ABAC library is a clear fit for a
GDPR-specific action set, so adopting one would mean bending its model to
fit rather than the reverse; second, learning a general-purpose policy
language would be a third new technology against a budget that already
allocates ABAC itself as one of exactly two (Rule D3) — the *concept* is the
learning objective, not a specific library's DSL.

**C — Custom lightweight ABAC engine: policies as versioned database rows,
evaluated by a single `PolicyEvaluator` service against a registry of named
sensitive actions.** Every sensitive action (DSAR identity verification,
DSAR export approval, DSAR erasure approval, retention policy execution,
audit log access) is registered once. Each policy row defines the action it
governs, a JSON-encoded set of subject/resource/environment conditions, and
an effect (allow/deny). The evaluator returns a decision plus the policy ID
and version that produced it, which is what gets logged.

## Decision

**Option C.** A custom, deliberately small ABAC engine, not a library and
not framework-native gates.

Separation of duties is implemented as a **condition on the
`dsar.erasure.approve` policy**: the policy's subject-attribute check
requires `actor.id != dsar.identity_verified_by_user_id`. This is expressed
as data (a policy row), not as a special-cased line of application code —
which means it shows up in the same policy registry, the same audit trail,
and the same exhaustive test suite as every other rule, rather than being an
exception nobody remembers to test.

## Trade-offs accepted

More code to write and test than framework gates would have required. This
is accepted because the auditability requirement (a policy ID per decision)
effectively forces a versioned, inspectable representation regardless — a
black-box gate can't produce that artifact no matter how it's implemented
internally.

## Consequences

- Every new sensitive action added in a later session must be registered in
  the policy registry before it can be gated — there is deliberately no
  "quick" path that bypasses the evaluator.
- The exhaustive authorisation test suite (NFR-005, built at Session 7)
  becomes possible to write mechanically: enumerate registered actions ×
  roles, assert the observed decision against the roles matrix.
- The separation-of-duties rule is now a data row, not code — changing it
  (e.g. requiring three-person approval later) is a policy update, not a
  deployment.

## Revisit triggers

- If the number of distinct sensitive actions grows beyond roughly 20, or
  policies start needing to reference multiple resources at once (cross-
  resource rules), consider adopting a mature policy language (e.g. Open
  Policy Agent/Rego) instead of extending the custom evaluator further.
  That would need its own ledger conversation, since it would be a new
  technology on top of an already-spent learning-budget slot.
- If a private-track product (e.g. PR01, PR10) needs the same pattern,
  extract this evaluator into a private shared module rather than
  duplicating it — but do not extract it into a *public* supporting
  repository unless a genuinely reusable, business-logic-free version can be
  produced (the current implementation embeds GDPR-specific action names).
