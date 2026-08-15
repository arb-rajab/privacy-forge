<?php

namespace App\Models;

use Database\Factories\DsarConnectorTaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One connector's independently tracked piece of a DSAR (US-007/FR-008).
// Terminal statuses are success|failed|partial; 'pending' is the only
// non-terminal one. See App\Services\DsarCompletionEvaluator for the
// rollup logic and App\Http\Controllers\ConnectorCallbackController for
// the idempotency/anomaly (T-08/T-09) rules around status transitions.
class DsarConnectorTask extends Model
{
    /** @use HasFactory<DsarConnectorTaskFactory> */
    use HasFactory;

    use HasUuids;

    public const TERMINAL_STATUSES = ['success', 'failed', 'partial'];

    protected $fillable = [
        'dsar_request_id',
        'connector_id',
        'task_type',
        'status',
        'attempt_count',
        'failure_reason',
        'dispatched_at',
        'completed_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DsarRequest, $this>
     */
    public function dsarRequest(): BelongsTo
    {
        return $this->belongsTo(DsarRequest::class);
    }

    /**
     * @return BelongsTo<Connector, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
