<?php

namespace App\Models;

use Database\Factories\ExportBundleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// US-008/FR-010. `download_token` is the unguessable, opaque key used by
// the public download endpoint (T-05) — the row's own uuid `id` is never
// exposed to an unauthenticated data subject, mirroring DsarRequest's
// status_token.
class ExportBundle extends Model
{
    /** @use HasFactory<ExportBundleFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'dsar_request_id',
        'download_token',
        'storage_path',
        'format',
        'signed_url_expires_at',
    ];

    protected $hidden = [
        'download_token',
    ];

    protected $casts = [
        'signed_url_expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DsarRequest, $this>
     */
    public function dsarRequest(): BelongsTo
    {
        return $this->belongsTo(DsarRequest::class);
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->signed_url_expires_at);
    }
}
