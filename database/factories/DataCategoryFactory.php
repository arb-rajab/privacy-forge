<?php

namespace Database\Factories;

use App\Models\DataCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataCategory>
 */
class DataCategoryFactory extends Factory
{
    protected $model = DataCategory::class;

    public function definition(): array
    {
        return [
            'name' => 'Withdrawn consent records',
            'description' => 'Consent records after withdrawal.',
            'sensitivity' => 'standard',
            'subject_table' => DataCategory::SUBJECT_TABLE_CONSENT_RECORDS,
        ];
    }

    public function forDsarRequests(): static
    {
        return $this->state(fn () => [
            'name' => 'Closed DSAR requests',
            'description' => 'DSAR requests past their statutory limitation period after closure.',
            'sensitivity' => 'elevated',
            'subject_table' => DataCategory::SUBJECT_TABLE_DSAR_REQUESTS,
        ]);
    }
}
