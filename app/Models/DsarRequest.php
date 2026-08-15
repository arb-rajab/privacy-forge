<?php

namespace App\Models;

use Database\Factories\DsarRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

// US-005/US-006. subject_identifier is encrypted at rest (Laravel's
// `encrypted` cast, keyed on APP_KEY) rather than one-way hashed like
// ConsentRecord — staff must be able to read the identity claim to
// perform the manual-verification stub (FR-020). subject_identifier_hash
// exists purely for the NFR-006 rate-limit lookup and is never used to
// reconstruct the original value.
class DsarRequest extends Model
{
    /** @use HasFactory<DsarRequestFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'subject_identifier',
        'subject_identifier_hash',
        'status_token',
        'request_type',
        'status',
        'identity_verified_by',
        'identity_verified_at',
        'erasure_approved_by',
        'erasure_approved_at',
    ];

    protected $hidden = [
        'subject_identifier',
        'subject_identifier_hash',
        'status_token',
    ];

    protected $casts = [
        'subject_identifier' => 'encrypted',
        'identity_verified_at' => 'datetime',
        'erasure_approved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function identityVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function erasureApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erasure_approved_by');
    }

    /**
     * @return HasMany<DsarConnectorTask, $this>
     */
    public function connectorTasks(): HasMany
    {
        return $this->hasMany(DsarConnectorTask::class);
    }

    /**
     * @return HasMany<ExportBundle, $this>
     */
    public function exportBundles(): HasMany
    {
        return $this->hasMany(ExportBundle::class);
    }

    /**
     * @return HasOne<DeletionCertificate, $this>
     */
    public function deletionCertificate(): HasOne
    {
        return $this->hasOne(DeletionCertificate::class);
    }

    // Same HMAC-over-APP_KEY approach as ConsentRecord::hashIdentifier —
    // not shared/extracted into a trait since these are two independent
    // one-off call sites, not a growing pattern yet.
    public static function hashIdentifier(string $subjectIdentifier): string
    {
        return hash_hmac('sha256', $subjectIdentifier, config('app.key'));
    }

    // Retention-driven anonymisation (US-012): severs both identifying
    // columns while keeping status/request_type/timestamps intact for
    // aggregate/statistical value — no delete-guard exists on this model
    // (unlike ConsentRecord), so retention's "erase" action calls the
    // plain delete() below directly; this method exists only for
    // "anonymise" policies.
    public function anonymise(): void
    {
        $this->forceFill([
            'subject_identifier' => 'anonymised',
            'subject_identifier_hash' => 'anonymised-'.(string) Str::uuid(),
        ])->save();
    }
}
