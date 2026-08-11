# ADR-0002 — Retention Dry-Run / Execution Parity

- **Date:** 2026-08-11
- **Status:** accepted

## Context

FR-012 and US-011 require that a retention policy's dry-run preview and its
real execution select *exactly* the same records given unchanged data. This
isn't a nice-to-have — a dry run that could diverge from the real run
defeats the entire purpose of previewing a deletion before it happens.

## Options considered

**A — Two separate code paths:** a read-only reporting query written
alongside a separate execution query. Rejected: any future change to
selection criteria (e.g. adding a new exclusion rule) requires remembering
to update both queries identically, and a missed update produces a silent,
untested divergence — exactly the failure mode the requirement exists to
prevent.

**B — A single selection service, consumed by two different modes.** A
`RetentionSelector` service takes a retention policy and returns the current
candidate record set. A `RetentionExecutor` service consumes that set and
either (a) reports it, with no side effects (dry run), or (b) acts on it per
the policy's post-expiry action and writes a deletion certificate (real
run). The selection logic exists in exactly one place.

## Decision

**Option B.** Selection and execution are architecturally separated, and
only the executor branches on mode — the selector never does.

## Trade-offs accepted

One extra layer of indirection (a selector service between the policy
definition and the executor) compared to writing the query inline. Accepted
because it converts "dry run and real run must match" from a manual
discipline into a structural guarantee: there is no code path by which they
*could* diverge, because there is only one selection code path.

## Consequences

- The parity test (`RetentionDryRunParityTest`, referenced in the RTM against
  GDPR Art. 5(1)(e)) becomes a direct assertion — run dry-run and real-run
  against identical fixture data, assert identical record ID sets — rather
  than an inference from reading two separate queries.
- Any future retention feature (e.g. a new exclusion rule, a legal-hold
  override) must be implemented in the selector, never duplicated into the
  executor's real-run branch as a shortcut.
- A dry run always produces an artifact (a preview report), just as a real
  run always produces a certificate — neither path is "free" in terms of
  what it leaves behind, which keeps both auditable.

## Revisit triggers

- If profiling at scale shows the shared selector is a genuine performance
  bottleneck for the real-run path specifically (e.g. because dry-run-only
  optimisations aren't applicable), consider splitting *only after*
  measuring — not speculatively. At this project's expected scale (a single
  organisation's data), this is not anticipated to be necessary.
