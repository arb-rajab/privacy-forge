<?php

namespace App\Models;

use Database\Factories\ConsentPurposeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentPurpose extends Model
{
    /** @use HasFactory<ConsentPurposeFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'lawful_basis',
        'status',
        'current_notice_id',
        'version',
        'data_category_id',
        'data_subjects_description',
    ];

    /**
     * @return HasMany<ConsentNotice, $this>
     */
    public function notices(): HasMany
    {
        return $this->hasMany(ConsentNotice::class, 'purpose_id');
    }

    /**
     * @return BelongsTo<ConsentNotice, $this>
     */
    public function currentNotice(): BelongsTo
    {
        return $this->belongsTo(ConsentNotice::class, 'current_notice_id');
    }

    /**
     * @return HasMany<ConsentRecord, $this>
     */
    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class, 'purpose_id');
    }

    // Session 12 (US-013/FR-016): the join RopaGenerator uses to derive a
    // purpose's retention period — see the migration that added this
    // column for why it didn't already exist.
    /**
     * @return BelongsTo<DataCategory, $this>
     */
    public function dataCategory(): BelongsTo
    {
        return $this->belongsTo(DataCategory::class);
    }

    public function hasActiveConsentRecords(): bool
    {
        return $this->consentRecords()->where('status', 'active')->exists();
    }
}
