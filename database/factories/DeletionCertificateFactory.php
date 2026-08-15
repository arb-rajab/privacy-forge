<?php

namespace Database\Factories;

use App\Models\DeletionCertificate;
use App\Models\DsarRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeletionCertificate>
 */
class DeletionCertificateFactory extends Factory
{
    protected $model = DeletionCertificate::class;

    public function definition(): array
    {
        return [
            'dsar_request_id' => DsarRequest::factory(),
            'summary' => 'All registered connectors confirmed erasure.',
            'exceptions' => null,
            'issued_at' => now(),
        ];
    }
}
