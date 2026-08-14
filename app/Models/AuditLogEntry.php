<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Append-only per ADR-0003 — see AuditLogger for the only supported way
// to create rows (it computes the hash chain). save()/delete() are
// overridden here so nothing can quietly bypass AuditLogger and write an
// un-chained or edited entry through the model directly.
class AuditLogEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sequence',
        'actor_user_id',
        'actor_type',
        'action',
        'resource_type',
        'resource_id',
        'policy_id',
        'decision',
        'prev_hash',
        'entry_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('AuditLogEntry rows are append-only; they cannot be modified after creation.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('AuditLogEntry rows cannot be deleted; the audit trail is retained indefinitely by design.');
    }
}
