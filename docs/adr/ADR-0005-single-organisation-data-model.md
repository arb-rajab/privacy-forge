# ADR-0005 — Single-Organisation Data Model (No Tenant Column)

- **Date:** 2026-08-11
- **Status:** accepted

## Context

FR-017 states the *business* requirement — exactly one organisation per
instance, no multi-tenancy — already decided at Session 1. This ADR fixes
*how that is implemented* at the schema level, because "no tenant model" is
easy to state and surprisingly easy to accidentally undermine in the data
model without a deliberate decision.

## Options considered

**A — Include an `org_id`/`tenant_id` column on every table "just in case,"
defaulting to a single value.** Cheap-looking future-proofing. Rejected: it
silently reopens the multi-tenancy non-goal that Session 1 explicitly closed
(with a stated reconsider-trigger), and it invites exactly the kind of
incremental scope creep the non-goals table exists to prevent — a tenant
column with no tenant *behaviour* is a half-built feature masquerading as a
design choice.

**B — No tenant concept anywhere in the schema.** Organisation-level
settings (name, DPO contact, jurisdiction confirmation) live in a single
settings table enforced to contain at most one row via an application-level
singleton constraint (a unique index on a constant key, or a check
constraint).

## Decision

**Option B.** There is no tenant concept in this schema at all, not even a
dormant one.

## Trade-offs accepted

If multi-tenancy were ever genuinely needed for *this* repository, migrating
away from a single-row settings model would be a bigger lift than adding
tenant scoping to pre-existing `tenant_id` columns would have been. This is
accepted deliberately: the portfolio's own governance draws a hard line
between public-track breadth decisions and private-track fitness decisions
(see `00-portfolio-strategy.md` §1.4), and building in unused multi-tenancy
scaffolding here would misrepresent this repository's actual, intentionally
narrow scope to a reviewer reading the schema. Multi-tenancy is a real,
substantial engineering problem — it belongs to private-track direction
PR02, built properly when it's actually needed, not half-present here as
insurance.

## Consequences

- No table in `04-data-model.md` carries a tenant/org foreign key.
- Every future session must resist adding one "for consistency with other
  repos" — this repository is deliberately single-tenant, and that is a
  stated, intentional contrast with PR02, not an oversight to fix.
- The application enforces the single-settings-row constraint at the
  database level (not just in application code), so even a bug in the
  application layer cannot create a second organisation's worth of settings.

## Revisit triggers

None anticipated for this repository specifically. A genuine multi-tenant
need should become private-track direction PR02 — a new repository built
for that requirement on its own terms — not a retrofit of this one.
