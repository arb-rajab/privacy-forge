<?php

namespace App\Models;

use Database\Factories\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

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
        throw new LogicException('ConsentRecord rows cannot be deleted; withdraw() the record instead.');
    }

    // GDPR Art. 5(1)(e) storage-limitation erasure (retention execution,
    // US-012) is a deliberately separate deletion path from withdrawal —
    // delete() above guards the withdrawal flow against accidentally
    // becoming destructive; it does not, and must not, block the
    // retention engine's own expiry-driven erasure, which is exactly what
    // this method exists to allow. Uses a query-builder delete (bypasses
    // the Eloquent instance method above, which always throws) rather
    // than routing around the guard silently — the bypass is explicit and
    // named, not accidental.
    public function retentionErase(): bool
    {
        return (bool) static::query()->whereKey($this->id)->delete();
    }

    // Anonymising severs the link to a specific subject (the only
    // personal-data element this row carries) while keeping the row for
    // aggregate/statistical value — unlike retentionErase(), this is a
    // plain update, so no delete-guard bypass is involved.
    public function anonymise(): void
    {
        $this->forceFill(['subject_identifier_hash' => 'anonymised-'.(string) Str::uuid()])->save();
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
