<?php

namespace App\Models;

use Database\Factories\ConsentNoticeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Immutable once created (04-data-model.md invariant) — no update() call
// is ever valid against an existing notice, so save()/update() are
// overridden to refuse if the row already has an id that exists in the
// database (i.e. this isn't the initial insert).
class ConsentNotice extends Model
{
    /** @use HasFactory<ConsentNoticeFactory> */
    use HasFactory;

    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'purpose_id',
        'version',
        'body',
        'published_at',
        'created_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ConsentPurpose, $this>
     */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'purpose_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('ConsentNotice rows are immutable once published; publish a new version instead.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('ConsentNotice rows cannot be deleted; they are the evidentiary record of what a data subject was shown.');
    }
}
