<?php

namespace Database\Factories;

use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DsarConnectorTask>
 */
class DsarConnectorTaskFactory extends Factory
{
    protected $model = DsarConnectorTask::class;

    public function definition(): array
    {
        return [
            'dsar_request_id' => DsarRequest::factory(),
            'connector_id' => Connector::factory(),
            'task_type' => 'export',
            'status' => 'pending',
            'attempt_count' => 0,
            'dispatched_at' => now(),
        ];
    }
}
