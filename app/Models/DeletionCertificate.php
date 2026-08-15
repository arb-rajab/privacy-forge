<?php

namespace App\Models;

use Database\Factories\DeletionCertificateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// US-009/FR-011. `exceptions` is nullable text: null means every
// connector confirmed erasure; a non-null value explicitly names which
// connector(s) could not confirm, because FR-011 forbids ever overstating
// what was achieved. See App\Services\DeletionCertificateGenerator.
//
// Shared table, two sources (Session 11 decision, see
// docs/project-memory/09-decision-log.md): a certificate is produced by
// either a DSAR erasure (US-009, `dsar_request_id` set) or a retention
// execution (US-012, `retention_execution_id` set), never both and never
// neither — enforced by a DB CHECK constraint
// (`deletion_certificates_exactly_one_source`), not just application
// convention.
class DeletionCertificate extends Model
{
    /** @use HasFactory<DeletionCertificateFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'dsar_request_id',
        'retention_execution_id',
        'summary',
        'exceptions',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DsarRequest, $this>
     */
    public function dsarRequest(): BelongsTo
    {
        return $this->belongsTo(DsarRequest::class);
    }

    /**
     * @return BelongsTo<RetentionExecution, $this>
     */
    public function retentionExecution(): BelongsTo
    {
        return $this->belongsTo(RetentionExecution::class);
    }
}
