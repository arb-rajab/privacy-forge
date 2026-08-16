<?php

namespace App\Services;

use App\Models\ConsentPurpose;
use App\Models\RetentionPolicy;

// US-013/FR-016 (Art. 30 RTM row). Generated on demand, not stored: there
// is no ROPA_RECORD table in 04-data-model.md's ERD — a RoPA is a report
// over CONSENT_PURPOSE (+ the retention data joined in below), never its
// own system of record, so it can never silently drift out of sync with
// the purposes/policies it describes the way a cached, stored copy could.
// Logged as a decision-log entry (09-decision-log.md), not a new ADR,
// since no existing ADR ever committed to a stored-RoPA design to reopen.
//
// FR-016/US-013 AC1 says "covering all active purposes" explicitly — a
// deprecated purpose is excluded, not merely hidden; historical
// accountability for a deprecated purpose is served by the audit log
// (which still has every consent_purpose.update/create entry), not by
// this report, which describes current processing activity.
class RopaGenerator
{
    /**
     * @return array<int, array{
     *     purpose_id: string,
     *     purpose_name: string,
     *     lawful_basis: string,
     *     data_category_name: string|null,
     *     data_category_description: string|null,
     *     data_subjects_description: string|null,
     *     retention_period_days: int|null,
     *     post_expiry_action: string|null,
     * }>
     */
    public function generate(): array
    {
        return ConsentPurpose::query()
            ->where('status', 'active')
            ->with('dataCategory')
            ->orderBy('name')
            ->get()
            ->map(function (ConsentPurpose $purpose): array {
                $category = $purpose->dataCategory;

                // A purpose only has "the categories of data subjects and
                // of the categories of personal data" (Art. 30(1)(c)) it
                // was actually given at creation/update time via the
                // data_category_id/data_subjects_description fields added
                // this session — nothing here fabricates a value for a
                // purpose that was never told one.
                $policy = $category !== null
                    ? RetentionPolicy::query()
                        ->where('data_category_id', $category->id)
                        ->where('status', 'active')
                        // Nothing in RetentionPolicyController::store
                        // prevents two independently-created 'active' rows
                        // for the same category (only ::update's
                        // supersede-then-create pattern guarantees
                        // uniqueness) — a pre-existing Session 11 gap, not
                        // this session's to close. Ordering by version
                        // then recency keeps this deterministic rather than
                        // picking whichever row the database happens to
                        // return first.
                        ->orderByDesc('version')
                        ->orderByDesc('created_at')
                        ->first()
                    : null;

                return [
                    'purpose_id' => $purpose->id,
                    'purpose_name' => $purpose->name,
                    'lawful_basis' => $purpose->lawful_basis,
                    'data_category_name' => $category?->name,
                    'data_category_description' => $category?->description,
                    'data_subjects_description' => $purpose->data_subjects_description,
                    'retention_period_days' => $policy?->retention_period_days,
                    'post_expiry_action' => $policy?->post_expiry_action,
                ];
            })
            ->all();
    }
}
