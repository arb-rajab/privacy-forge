# ADR-0007 — Cross-Field Comparison Operator in Policy Conditions

- **Date:** 2026-08-14
- **Status:** accepted

## Context

ADR-0001 already named the shape separation-of-duties would take: "a
condition on the `dsar.erasure.approve` policy" requiring
`actor.id != dsar.identity_verified_by_user_id`, expressed as data (a
policy row), not a special-cased line of application code. Session 6b
built the first real `PolicyEvaluator` and confirmed, by trying to write
this policy for real, that its condition matcher does not yet have a
way to express it: `matchesConditions()` only supports `in`/`equals`,
both of which compare one attribute against a *fixed value* baked into
the policy row. Separation-of-duties needs to compare one attribute of
the request (the approving actor's ID) against another attribute of the
same request (the DSAR's `identity_verified_by`) — a relationship
between two fields, not a field against a constant. This is a different
shape of condition, not a missing value in an existing shape, so
extending `PolicyEvaluator` deserves its own decision record rather than
being folded silently into the `dsar.erasure.approve` policy row as if
it were just another ordinary policy.

## Options considered

**A — Extend the condition matcher with a general cross-field comparison
operator.** Add `not_equals_attribute` (value: a `"bag.attribute"`
reference string, e.g. `"resource.identity_verified_by"`), resolved
against all three attribute bags (`subject`/`resource`/`environment`)
regardless of which bag the condition itself lives in. Separation-of-duties
becomes an ordinary policy row like every other rule.

**B — Special-case the comparison in the erasure-approval controller.**
Fetch the DSAR's `identity_verified_by`, compare it to the acting user's
ID directly in `Admin\DsarController::approveErasure`, and deny before
ever calling `PolicyEvaluator::evaluate()`. Simpler to write, and
genuinely sufficient for exactly one rule.

## Decision

**Option A.** The condition matcher now supports `not_equals_attribute`
alongside the existing `in`/`equals` operators. This is chosen over
Option B because Option B would directly contradict what ADR-0001
already decided and why: separation-of-duties was deliberately specified
as a policy row *because* a hardcoded comparison "shows up in the same
policy registry, the same audit trail, and the same exhaustive test
suite as every other rule, rather than being an exception nobody
remembers to test" (ADR-0001, Decision section). Special-casing it in
the controller now, one session later, would quietly reverse that
decision without saying so — exactly the "wrong outcome" this ADR exists
to avoid. The `dsar.erasure.approve` policy row expresses both of its
gates as ordinary conditions:

```json
{
  "subject_conditions": {
    "role": {"in": ["owner", "privacy_manager"]},
    "id": {"not_equals_attribute": "resource.identity_verified_by"}
  },
  "resource_conditions": {
    "status": {"in": ["in_progress"]},
    "request_type": {"in": ["erasure"]}
  }
}
```

The `resource_conditions.status` check is what makes FR-007/US-006 AC2
("no export or erasure task executes before identity verification") a
policy-level guarantee too, for exactly the same reason: a DSAR still in
`pending_verification` has no `identity_verified_by` to compare against,
and it is a policy row — not a controller `if` — that refuses it.

## Trade-offs accepted

The condition matcher's surface area grows: a reader of a policy row can
no longer assume every condition compares against a literal value, and
`PolicyEvaluator` must now pass every attribute bag (subject, resource,
environment) into every `matchesConditions()` call so cross-bag
references can resolve, not just the bag currently being checked. A
malformed or unresolvable reference (an unknown bag, a non-string value)
throws `UnexpectedValueException`, caught by `evaluate()`'s existing
fail-closed `catch(Throwable)` — this reuses ADR-0006's guarantee rather
than inventing a second failure path, but it does mean a typo'd
attribute reference degrades to "silently denies everyone" rather than a
config-time validation error, since there is still no `policy.update`
action or schema validator for policy rows (tracked by `R-02`).

## Consequences

- This does not reopen ADR-0001's core decision (a custom, versioned,
  database-row ABAC engine) or ADR-0006's fail-closed default — it
  extends what the existing condition DSL can express, consistent with
  what ADR-0001 already said this specific rule would look like.
- Future sensitive actions needing a similar "these two attributes must
  differ" or "these two attributes must match" rule (e.g. a future
  four-eyes rule elsewhere) can reuse `not_equals_attribute` (or a
  future `equals_attribute` sibling, not added now since nothing needs
  it yet) rather than each inventing its own comparison.
- The exhaustive authorisation test suite (NFR-005, Session 7) must
  account for cross-field conditions specifically — enumerating
  (role × action) pairs alone doesn't exercise "same actor for both
  steps", which needs a scenario with two related requests, not one.

## Revisit triggers

- If a policy ever needs to compare more than two attributes, or chain
  multiple cross-field comparisons with boolean logic beyond implicit
  AND, treat that as a sign the condition DSL is outgrowing what a hand
  rolled matcher should carry — this is one of ADR-0001's own revisit
  triggers (adopting a mature policy language) rather than a new one.
