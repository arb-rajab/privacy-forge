# ADR-0004 — Connector Webhook Contract Shape

- **Date:** 2026-08-11
- **Status:** accepted

## Context

FR-008 and FR-009 require DSAR export/erasure tasks to be dispatched to N
registered connectors, each independently tracked, with partial failure
surfaced rather than hidden. FR-019 requires the contract to be documented
and provable via a reference/stub connector, without any specific real
third-party connector shipping in v1 (a Session 1 non-goal).

## Options considered

**A — Synchronous HTTP call per connector at request time.** Simplest to
implement, but a slow or unavailable connector blocks the entire DSAR
action, and there's no natural place to track "this specific connector task
is still pending" independently of the others — which directly conflicts
with the "independently tracked" requirement in US-007.

**B — Asynchronous, queue-based dispatch with signed webhooks and a signed
callback.** Actioning a verified DSAR enqueues one job per registered
connector. Each job sends a signed (HMAC + timestamp) webhook POST to the
connector describing the task. The connector performs its work and calls
back to a signed, connector-authenticated endpoint reporting success,
failure, or partial completion. Retries use exponential backoff up to a
configured attempt limit; exhausting retries marks that specific task
`failed`, which is what makes the DSAR as a whole `partially_complete`
rather than silently `complete`.

## Decision

**Option B.** This fits directly on top of infrastructure already planned
for other reasons (Redis + a queue worker for retention scheduling), so it
doesn't introduce new infrastructure — only a new usage of it.

## Trade-offs accepted

The connector side now has to implement two things, not one: an inbound
webhook receiver *and* an outbound callback caller. This is more integration
work for a connector author than a single synchronous call would have been.
Accepted because the "independently tracked, partial-failure-visible"
requirement is a hard constraint, and a synchronous model cannot satisfy it
without a different set of compromises (timeouts, blocking behaviour) that
are worse for this product's specific failure-visibility priority.

## Consequences

- The public callback endpoint is new attack surface that did not exist in
  a synchronous design — it must be authenticated with connector-specific
  credentials (not the same credentials as staff/admin access), and this is
  flagged explicitly forward to the Session 4 threat model as a named trust
  boundary, not left implicit.
- Only one connector — a reference/stub built specifically to prove the
  contract — ships in v1, per the Session 1 non-goal on real third-party
  connectors. The contract must be documented precisely enough that a
  private-track engagement could implement a real connector against it
  without needing to read this repository's source.

## Revisit triggers

- If a real private-track connector integration reveals the contract
  doesn't fit a specific system's constraints (e.g. a system that can only
  poll, not receive webhooks), evolve the contract via an explicit version
  bump in the webhook schema rather than a breaking change to v1's contract.
