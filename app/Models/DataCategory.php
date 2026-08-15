<?php

namespace App\Models;

use Database\Factories\DataCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// DATA_CATEGORY per 04-data-model.md (US-010). `subject_table` is a
// Session 11 addition to the ERD (see the create_data_categories_table
// migration) naming which of this instance's own tables a governing
// RetentionPolicy actually queries — RetentionSelector switches on it,
// and it is the only place that mapping is allowed to live.
class DataCategory extends Model
{
    /** @use HasFactory<DataCategoryFactory> */
    use HasFactory;

    use HasUuids;

    public const SUBJECT_TABLE_CONSENT_RECORDS = 'consent_records';

    public const SUBJECT_TABLE_DSAR_REQUESTS = 'dsar_requests';

    protected $fillable = [
        'name',
        'description',
        'sensitivity',
        'subject_table',
    ];

    /**
     * @return HasMany<RetentionPolicy, $this>
     */
    public function retentionPolicies(): HasMany
    {
        return $this->hasMany(RetentionPolicy::class);
    }
}
