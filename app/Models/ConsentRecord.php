<?php

namespace App\Models;

use Database\Factories\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Withdrawal is an update to status/withdrawn_at only — never a delete
// (04-data-model.md invariant). delete() is overridden to refuse outright
// so withdrawal can't accidentally regress into a destructive call.
class ConsentRecord extends Model
{
    /** @use HasFactory<ConsentRecordFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'subject_identifier_hash',
        'purpose_id',
        'notice_id',
        'status',
        'given_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'given_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ConsentPurpose, $this>
     */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'purpose_id');
    }

    /**
     * @return BelongsTo<ConsentNotice, $this>
     */
    public function notice(): BelongsTo
    {
        return $this->belongsTo(ConsentNotice::class, 'notice_id');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('ConsentRecord rows cannot be deleted; withdraw() the record instead.');
    }

    public static function isActiveFor(string $subjectIdentifierHash, string $purposeId): bool
    {
        return static::query()
            ->where('subject_identifier_hash', $subjectIdentifierHash)
            ->where('purpose_id', $purposeId)
            ->where('status', 'active')
            ->exists();
    }

    // Plaintext subject_identifier (e.g. an email) is never stored — only
    // this keyed hash, per the "no index on subject_identifier in
    // plaintext" note in 04-data-model.md's indexing strategy. Keyed on
    // APP_KEY so the hash can't be reproduced without the app's own
    // secret, unlike a bare sha256() of the identifier.
    public static function hashIdentifier(string $subjectIdentifier): string
    {
        return hash_hmac('sha256', $subjectIdentifier, config('app.key'));
    }
}
