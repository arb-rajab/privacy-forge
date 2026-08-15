<?php

namespace App\Http\Controllers;

use App\Models\ExportBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

// US-008/FR-010 (NFR-007: ≤72h TTL). `signedToken` here is
// ExportBundle::download_token — an opaque, unguessable per-bundle key
// (T-05), the same pattern as DsarController::status's status_token —
// wrapped in Laravel's own request signature via
// URL::temporarySignedRoute wherever this link is generated.
//
// TTL is enforced twice, per 04-data-model.md's invariant table: once by
// Laravel's own signature (bounded by the URL's expiry at generation
// time) and again here against the row's own signed_url_expires_at,
// independent of what the URL's signature alone would allow — so a bug
// or a since-revoked link can't outlive the row's own recorded expiry.
class ExportBundleController extends Controller
{
    public function download(Request $request, string $signedToken): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->expired();
        }

        $bundle = ExportBundle::query()->where('download_token', $signedToken)->first();

        if ($bundle === null || $bundle->isExpired()) {
            return $this->expired();
        }

        // A short-lived, single-purpose link for the bytes themselves,
        // generated fresh on every call rather than stored — this app
        // serves the file itself rather than delegating to an S3-native
        // presigned URL, keeping local disk and S3 behaviourally
        // identical for this endpoint.
        $rawLinkExpiry = now()->addMinutes(15);
        if ($bundle->signed_url_expires_at->lessThan($rawLinkExpiry)) {
            $rawLinkExpiry = $bundle->signed_url_expires_at;
        }

        $rawUrl = URL::temporarySignedRoute('dsar.export.raw', $rawLinkExpiry, ['bundleId' => $bundle->id]);

        return response()->json([
            'format' => $bundle->format,
            'download_url' => $rawUrl,
            'expires_at' => $bundle->signed_url_expires_at->toIso8601String(),
        ]);
    }

    public function raw(Request $request, string $bundleId): Response|JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->expired();
        }

        $bundle = ExportBundle::query()->find($bundleId);

        if ($bundle === null || $bundle->isExpired()) {
            return $this->expired();
        }

        $encrypted = Storage::disk('s3')->get($bundle->storage_path);

        if ($encrypted === null) {
            return $this->expired();
        }

        $contents = Crypt::decryptString($encrypted);

        $contentType = $bundle->format === 'csv' ? 'text/csv' : 'application/json';

        return response($contents, 200, ['Content-Type' => $contentType]);
    }

    private function expired(): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Link expired',
            'status' => 410,
            'detail' => 'This download link is invalid or has expired; submit a new request for a fresh one.',
        ], 410);
    }
}
