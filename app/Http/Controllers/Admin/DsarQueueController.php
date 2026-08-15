<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DsarQueueItemResource;
use App\Models\DsarRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Staff-facing DSAR queue (Session 10) — closes the gap flagged after
// Session 8: per-connector task status was previously visible only via
// direct DB access. Read-only visibility into DSAR status is not one of
// ADR-0001's enumerated sensitive actions (DSAR verification, erasure
// approval, retention execution, audit log access, policy.update), and
// per the roles matrix (02-requirements.md) every staff role — including
// Support Staff, who cannot approve or execute anything — "can view DSAR
// status". So this is gated the same way ConsentPurposeController's
// non-sensitive actions are: a plain, any-authenticated-staff check, not
// a PolicyEvaluator call.
class DsarQueueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        if (! $request->user() instanceof User) {
            abort(401);
        }

        $dsars = DsarRequest::query()
            ->with(['connectorTasks.connector'])
            ->orderByDesc('created_at')
            ->get();

        return DsarQueueItemResource::collection($dsars);
    }
}
