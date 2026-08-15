<?php

namespace App\Models;

use Database\Factories\RetentionExecutionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// RETENTION_EXECUTION per 04-data-model.md (US-011/US-012, ADR-0002). A
// single dry-run or real run of a policy, produced by
// App\Services\RetentionExecutor — never written directly by a
// controller or the scheduled command, so the mode/count/certificate
// fields stay consistent with what the executor actually did.
class RetentionExecution extends Model
{
    /** @use HasFactory<RetentionExecutionFactory> */
    use HasFactory;

    use HasUuids;

    public const MODE_DRY_RUN = 'dry_run';

    public const MODE_REAL = 'real';

    protected $fillable = [
        'retention_policy_id',
        'mode',
        'affected_record_count',
        'certificate_id',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RetentionPolicy, $this>
     */
    public function retentionPolicy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class);
    }

    /**
     * @return HasOne<DeletionCertificate, $this>
     */
    public function deletionCertificate(): HasOne
    {
        return $this->hasOne(DeletionCertificate::class);
    }
}
