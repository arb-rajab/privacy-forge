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
}
