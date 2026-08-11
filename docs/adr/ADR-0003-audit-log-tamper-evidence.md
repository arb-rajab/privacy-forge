# ADR-0003 — Audit Log Tamper-Evidence Design

- **Date:** 2026-08-11
- **Status:** accepted

## Context

FR-014 and NFR-009 require that tampering with audit log entries be
detectable, not merely discouraged. This is the mechanism that makes the
rest of the product's evidentiary claims (RoPA exports, deletion
certificates, DSAR handling) actually trustworthy rather than merely
asserted.

## Options considered

**A — Database-level immutability only.** Revoke `UPDATE` and `DELETE`
grants on the audit log table for the application's database role. Cheap
and effective against the application itself misbehaving, but does **not**
detect tampering by someone with direct database access (a compromised
superuser credential, or a restored backup that's been edited before
restoration) — exactly the threat model a compliance product's own audit
trail should assume is possible.

**B — Hash-chained entries with periodic external anchoring.** Each entry
stores `hash(previous_entry_hash + this_entry's_content)`. Verification
replays the chain and confirms every stored hash matches its recomputed
value. On its own, a hash chain detects tampering with *any single entry*,
but an attacker with enough access to edit an entry could, in principle,
also recompute every subsequent hash to make the doctored chain internally
consistent again — unless the current chain root is periodically anchored
somewhere outside the attacker's reach (e.g. included in a signed release
artifact, or pushed to an external log the application itself cannot edit).

**C — Full distributed ledger / blockchain.** Rejected outright as
disproportionate: this is a single-instance, self-hosted application, and
the operational burden of running or depending on a distributed ledger has
no proportionate benefit here. It would also constitute a third new
learning-budget technology, which the ledger confirmation (Session 0)
explicitly caps at two.

## Decision

**A combined with B.** Revoke direct `UPDATE`/`DELETE` grants at the
database level *and* hash-chain every entry, with a scheduled job (built at
Session 8) that anchors the current chain root outside the live database —
for example, by including it in a signed release note or pushing it to a
separate, append-only external log the application process cannot itself
modify.

This is deliberately layered: DB-level grants stop the application (and
casual/accidental access) from editing history; the hash chain makes
in-place edits to stored entries mathematically detectable; anchoring closes
the remaining gap where a sufficiently privileged attacker edits the entries
*and* recomputes the chain to match.

## Trade-offs accepted

Anchoring adds an operational dependency (a scheduled job, and somewhere
external to push the anchor to) that a hash chain alone would not need. This
is accepted because, stated honestly: **a hash chain alone only protects
against tampering that doesn't also recompute the chain.** Anchoring is what
makes the tamper-evidence claim meaningful against a realistic attacker
rather than only against accidental corruption.

## Consequences

- Chain verification becomes a documented runbook item (Session 8), not a
  one-off script — it must be run routinely, and its result must be visible.
- If anchor storage becomes unavailable, tamper-detection strength degrades
  to "chain-only" (still detects single-entry edits, but not a
  sophisticated full-chain rewrite). This degradation must trigger an alert,
  not fail silently — recorded here so Session 8 doesn't treat anchoring as
  a "nice to have" that can quietly go unmonitored.
- The accepted residual risk is explicit: this design does not claim
  cryptographic non-repudiation to the standard of a formal timestamping
  authority (RFC 3161). It claims tamper *evidence*, not tamper
  *impossibility*.

## Revisit triggers

- If a private-track engagement (PR05 invoicing, or PR10 attestation) needs
  a formal, legally-recognised timestamp rather than tamper-evidence, that
  is a genuinely different and stronger requirement — implement RFC 3161
  timestamping there specifically, rather than retrofitting it here where
  it would exceed this repo's stated scope.
