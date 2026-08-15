<?php

namespace App\Services;

use App\Jobs\DispatchConnectorTaskJob;
use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;

// US-007/FR-008 (ADR-0004). Called from both verification paths that can
// action a DSAR: Admin\DsarController::verifyIdentity (export/access —
// there is no separate approval gate for those request types) and
// Admin\DsarController::approveErasure (erasure — gated further by
// separation of duties, ADR-0007). One DsarConnectorTask row per
// currently-active registered connector, each independently tracked.
//
// request_type 'access' is dispatched as task_type 'export': the ERD
// (04-data-model.md) only defines export|erasure for
// DSAR_CONNECTOR_TASK.task_type — an access request fundamentally needs
// the same "collect from every connector" mechanism as an export request,
// so no third task_type value was invented for it.
class DsarDispatcher
{
    public function dispatch(DsarRequest $dsar, string $taskType): void
    {
        $connectors = Connector::query()->where('status', 'active')->get();

        foreach ($connectors as $connector) {
            $task = DsarConnectorTask::create([
                'dsar_request_id' => $dsar->id,
                'connector_id' => $connector->id,
                'task_type' => $taskType,
                'status' => 'pending',
            ]);

            DispatchConnectorTaskJob::dispatch($task->id);
        }
    }
}
