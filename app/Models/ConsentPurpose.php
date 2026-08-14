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

    public function hasActiveConsentRecords(): bool
    {
        return $this->consentRecords()->where('status', 'active')->exists();
    }
}
