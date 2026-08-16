<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\DataCategory;
use App\Models\DsarRequest;
use App\Models\RetentionPolicy;
use Illuminate\Database\Eloquent\Builder;
use UnexpectedValueException;

// ADR-0002 — the ONLY place retention candidate-selection logic lives.
// RetentionExecutor (dry-run preview and real execution alike) consumes
// this query; neither mode branches the selection itself, only what
// happens to the records afterward. This is what makes "dry-run and real
// execution select exactly the same records" a structural guarantee
// rather than a discipline two separate queries would have to maintain by
// hand (see the ADR's Option A, rejected for exactly that reason).
//
// Deliberately excludes audit_log_entries and deletion_certificates:
// DataCategory::subject_table's DB enum only allows
// consent_records/dsar_requests, so there is no code path here by which
// either evidentiary table could ever be selected — 03-architecture.md's
// Backup and Recovery section is explicit that those two are exempt from
// the organisation's own retention policies, unlike consent_records and
// dsar_requests (04-data-model.md, "Retention and deletion rules").
class RetentionSelector
{
    /**
     * @return Builder<ConsentRecord>|Builder<DsarRequest>
     */
    public function query(RetentionPolicy $policy): Builder
    {
        $cutoff = now()->subDays($policy->retention_period_days);

        // data_category_id is a NOT NULL foreign key (the migration never
        // makes it nullable) so this is unreachable in practice; the
        // explicit check is what keeps phpstan's inference stable against
        // BelongsTo's nullable return type, the same pattern
        // DeletionCertificateGenerator::connectorName() already uses.
        $category = $policy->dataCategory;
        if ($category === null) {
            throw new UnexpectedValueException("Retention policy {$policy->id} has no data category.");
        }

        // subject_table's DB enum has exactly two values, so match() below
        // is already exhaustive over its type — no default arm; an
        // UnhandledMatchError is Laravel's own safety net if that were
        // ever untrue.
        return match ($category->subject_table) {
            DataCategory::SUBJECT_TABLE_CONSENT_RECORDS => ConsentRecord::query()
                // Only withdrawn consent is eligible — 04-data-model.md's
                // data classification table is explicit that consent data
                // is "never auto-deleted while a related lawful-basis
                // question is open," i.e. while it is still active.
                ->where('status', 'withdrawn')
                ->whereNotNull('withdrawn_at')
                ->where('withdrawn_at', '<=', $cutoff)
                // A prior anonymise run already applied this policy's
                // consequence to this row (erase instead removes the row
                // outright, so it simply can't reappear here — this
                // exclusion only matters for the anonymise action). Without
                // it, an anonymised row's status/withdrawn_at are left
                // untouched by design (retentionErase() keeps the row for
                // aggregate value), so every subsequent scheduled run would
                // re-select and re-certify it forever.
                ->where('subject_identifier_hash', 'not like', 'anonymised-%'),
            DataCategory::SUBJECT_TABLE_DSAR_REQUESTS => DsarRequest::query()
                // Only DSARs that have reached a terminal state are
                // eligible — an in-progress or pending request is still
                // open work, not yet subject to a post-closure retention
                // clock.
                ->whereIn('status', ['complete', 'partially_complete', 'rejected'])
                ->where('created_at', '<=', $cutoff)
                // Same re-selection guard as above: DsarRequest::anonymise()
                // leaves status/created_at untouched, so this exclusion is
                // what stops an already-anonymised request from being
                // re-processed by every future scheduled run.
                ->where('subject_identifier_hash', 'not like', 'anonymised-%'),
        };
    }
}
