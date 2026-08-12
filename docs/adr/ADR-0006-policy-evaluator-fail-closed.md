# ADR-0006 — Fail-Closed Default for the PolicyEvaluator

- **Date:** 2026-08-12
- **Status:** accepted
- **Surfaced during:** Session 4 (Security & Privacy Design) — not anticipated
  at Session 3, added rather than silently folded into ADR-0001 because it's
  a distinct decision with its own trade-off, not a detail of the original one.

## Context

ADR-0001 established a custom ABAC evaluator as the single gate for every
sensitive action. It did not specify what the evaluator does when something
goes wrong: a missing policy for a registered action, a malformed policy
condition, an unexpected exception during evaluation, or a database
timeout while fetching policy rows. Left unspecified, an evaluator is at
real risk of defaulting to "allow" in exactly the cases where the system is
least sure what it's doing — which is the worst possible failure mode for a
compliance product whose entire premise is defensible access control.

## Options considered

**A — Fail-open (default allow) on evaluator error.** Keeps the system
"working" through transient errors, minimising support burden. Rejected
outright: it means a bug, a missing policy row, or a database blip silently
grants access to erasure, export approval, or audit log viewing — the exact
actions this repository exists to gate carefully. This would also directly
contradict FR-013 ("every sensitive action is authorised via ABAC policy
evaluation"), since fail-open on error means some actions are, in effect,
authorised by *nothing*.

**B — Fail-closed (default deny) on any evaluator error, with the failure
itself logged as a denial with a distinguishing reason code.** The system
becomes less available under fault conditions but never silently permissive.

## Decision

**Option B.** The `PolicyEvaluator` denies by default whenever it cannot
reach a clear, unambiguous "allow" — including missing policies, malformed
conditions, exceptions during evaluation, and data-access failures while
fetching policy rows. Every fail-closed denial is written to the audit log
with `decision=deny` and a reason code distinguishing it from an ordinary,
correctly-evaluated denial (e.g. `policy_missing`, `evaluation_error`), so
an operator can tell "this was denied by design" apart from "the evaluator
itself is broken and needs attention."

This mirrors the fail-closed stance already taken elsewhere in the
portfolio for exactly the same reason (see the admission-webhook design in
the `forge-supply` flagship) — availability is the acceptable cost when the
alternative is a silent authorisation gap in a security-critical path.

## Trade-offs accepted

A bug or outage in the policy-evaluation path now degrades to "nothing
sensitive can be approved" rather than "everything is quietly approved."
This is a real availability cost — a Privacy Manager cannot verify identity
or approve erasure while the evaluator is unhealthy — and it is accepted
deliberately, because the alternative failure mode is unacceptable for this
product's specific purpose.

## Consequences

- A missing or malformed policy is now a **production incident**, not a
  silent pass-through — it must be visible (an alert, not just a log line
  nobody reads), and this is carried forward to Session 8's observability
  design as a named alert condition.
- Modifying policy definitions is itself added to the sensitive-action
  registry (ADR-0001) as `policy.update`, restricted to the Owner role and
  audit-logged like every other sensitive action — a policy that can be
  changed by anyone, or changed without a trace, would undermine the whole
  fail-closed guarantee by letting someone quietly weaken it.
- The exhaustive authorisation test suite (NFR-005, Session 7) must include
  fault-injection cases (a deliberately missing policy, a malformed
  condition) asserting the observed outcome is `deny`, not just testing the
  happy-path allow/deny matrix.

## Revisit triggers

None anticipated — fail-closed is treated as a durable architectural
property of this system, not a provisional choice. If a future session finds
a specific action where fail-closed is genuinely wrong (e.g. a low-stakes
read that shouldn't block on evaluator health), that action should be
reconsidered for removal from the sensitive-action registry entirely, not
used as a reason to weaken the default for the registry as a whole.
