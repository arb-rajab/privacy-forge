<?php

namespace Database\Factories;

use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentRecord>
 */
class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecord::class;

    public function definition(): array
    {
        $purpose = ConsentPurpose::factory()->create();
        $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

        return [
            'subject_identifier_hash' => ConsentRecord::hashIdentifier(fake()->unique()->safeEmail()),
            'purpose_id' => $purpose->id,
            'notice_id' => $notice->id,
            'status' => 'active',
            'given_at' => now(),
        ];
    }
}
