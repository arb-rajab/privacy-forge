<?php

namespace App\Models;

use Database\Factories\RetentionPolicyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// RETENTION_POLICY per 04-data-model.md (US-010/FR-012). Versioned rows,
// same pattern as PolicyDefinition: `data_category_id` is the grouping
// key across versions (mirroring PolicyDefinition's `action_name`) — an
// update supersedes the current active row for that category and creates
// version+1, never mutates in place.
class RetentionPolicy extends Model
{
    /** @use HasFactory<RetentionPolicyFactory> */
    use HasFactory;

    use HasUuids;

    public const ACTION_ERASE = 'erase';

    public const ACTION_ANONYMISE = 'anonymise';

    protected $fillable = [
        'data_category_id',
        'retention_period_days',
        'post_expiry_action',
        'status',
        'version',
    ];

    /**
     * @return BelongsTo<DataCategory, $this>
     */
    public function dataCategory(): BelongsTo
    {
        return $this->belongsTo(DataCategory::class);
    }

    /**
     * @return HasMany<RetentionExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(RetentionExecution::class);
    }
}
