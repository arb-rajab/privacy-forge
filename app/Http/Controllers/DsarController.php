<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDsarRequest;
use App\Http\Resources\DsarStatusResource;
use App\Models\DsarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Public endpoints (FR-005, FR-006, US-005) — no staff auth, matching the
// DSAR Portal tag in docs/architecture/openapi.yaml.
class DsarController extends Controller
{
    public function submit(SubmitDsarRequest $request): JsonResponse
    {
        $data = $request->validated();
        $identifierHash = DsarRequest::hashIdentifier($data['subject_identifier']);

        // NFR-006 — keyed on the identifier, not the caller, so the limit
        // holds regardless of how many different IPs a submitter uses.
        $rateLimitKey = 'dsar-submission:'.$identifierHash;
        $maxPerDay = (int) config('dsar.submission_rate_limit_per_day');

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxPerDay)) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Too Many Requests',
                'status' => 429,
                'detail' => 'This identifier has submitted too many DSARs in the last 24 hours.',
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 60 * 60 * 24);

        $dsar = DsarRequest::create([
            'subject_identifier' => $data['subject_identifier'],
            'subject_identifier_hash' => $identifierHash,
            'status_token' => Str::random(64),
            'request_type' => $data['request_type'],
            'status' => 'pending_verification',
        ]);

        // T-05 — the public link carries an opaque status_token, never
        // the row's own uuid id, and is both signed and time-limited
        // (NFR-007) via Laravel's own signed-route mechanism.
        $ttlHours = (int) config('dsar.status_link_ttl_hours');
        $statusUrl = URL::temporarySignedRoute(
            'dsar.status',
            now()->addHours($ttlHours),
            ['signedToken' => $dsar->status_token],
        );

        return response()->json([
            'status_url' => $statusUrl,
            'status' => $dsar->status,
        ], 201);
    }

    public function status(Request $request, string $signedToken): JsonResponse|DsarStatusResource
    {
        // Deliberately not the 'signed' middleware: that throws a 403 on
        // both "expired" and "tampered", but the OpenAPI contract only
        // documents 410 for this endpoint — and collapsing both cases
        // into the same response avoids giving an attacker an oracle for
        // "this token once existed" vs "this token is simply invalid"
        // (T-05, 06-security-threat-model.md).
        if (! $request->hasValidSignature()) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Link expired',
                'status' => 410,
                'detail' => 'This status link is invalid or has expired; submit a new request for a fresh one.',
            ], 410);
        }

        $dsar = DsarRequest::query()->where('status_token', $signedToken)->firstOrFail();

        return new DsarStatusResource($dsar);
    }
}
