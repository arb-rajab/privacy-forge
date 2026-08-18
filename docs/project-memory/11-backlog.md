# Backlog
> Purpose: everything deliberately not being done right now
> Project: privacy-forge (public)
> Last updated: 2026-08-17 (Session 19)

## Next up
| ID | Item | Type | Size | Why now |
|---|---|---|---|---|
| B-01 | Full-instance archival export (`php artisan privacy-forge:export-instance` or similar) — one JSON+CSV export covering every consent record, DSAR, retention execution, deletion certificate, and the audit log at once, reusing `ExportBundleAssembler`/`RopaController`'s existing JSON/CSV shaping conventions rather than a new format. Identified as a genuine gap at Session 19 (`14-maintenance-and-retirement.md`, "Archival export format") — today's only available substitute is a raw `pg_dump`, which is not guaranteed to `pg_restore` cleanly across future PostgreSQL major versions. | Feature | Medium | Closes the one real gap in the instance-decommissioning runbook; not built at Session 19 per that session's explicit scope boundary (a new, non-trivial feature, not a "trivial missing piece") |
| B-02 | `RetentionPolicyController::store` does not prevent two independently-created `active` policies for the same `data_category_id` (only the `update` path's supersede-then-create flow guarantees uniqueness) — a Session 11 gap, restated at Session 12 and again at Session 19 (`14-maintenance-and-retirement.md`), previously undertracked here. | Bug | Small | A real, if narrow, data-integrity gap in the retention engine; low urgency (requires two independent create calls racing, not a normal UI flow) but cheap to fix once picked up |
| B-03 | Weekly `schedule:`-triggered re-run of the existing `osv-scanner` CI job — currently only runs `on: push`/`pull_request` to `main` (`.github/workflows/ci.yml`), so a CVE published against an already-merged dependency with no further pushes is never re-caught. Identified at Session 19 (`14-maintenance-and-retirement.md`, "Maintenance cadence"). | Chore | Small | Cheap (reuses the existing job, just adds a trigger); not added at Session 19 to avoid touching CI config under a documentation-focused session |
## Later
## Explicitly rejected (with reasons)
