<?php

namespace App\Http\Resources;

use App\Models\DsarRequest;
use App\Models\ExportBundle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

// Field names/shape match components.schemas.DsarStatus in
// docs/architecture/openapi.yaml exactly. Deliberately excludes
// subject_identifier and status_token — this is returned to a caller who
// has proven ownership via a signed link or staff session, but the raw
// identity claim and the internal status token still have no reason to
// leave the server.
//
// export_bundles/deletion_certificate (Session 10): before this session,
// nothing ever minted a signed download URL for a data subject to learn
// their export was ready (ExportBundle::download_token existed, and
// dsar.export.download was a named route, but no caller ever built a
// signed link to it) — the gap Session 8 flagged. This is the one link a
// data subject already holds (the status link from DsarController::submit),
// so it's the natural place to surface both, rather than inventing a new
// endpoint. Expired bundles are filtered out rather than listed with a
// dead link.
/**
 * @mixin DsarRequest
 */
class DsarStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $certificate = $this->deletionCertificate;

        return [
            'id' => $this->id,
            'request_type' => $this->request_type,
            'status' => $this->status,
            'export_bundles' => $this->exportBundles
                ->reject(fn (ExportBundle $bundle): bool => $bundle->isExpired())
                ->map(fn (ExportBundle $bundle): array => [
                    'format' => $bundle->format,
                    'download_url' => URL::temporarySignedRoute(
                        'dsar.export.download',
                        $bundle->signed_url_expires_at,
                        ['signedToken' => $bundle->download_token],
                    ),
                    'expires_at' => $bundle->signed_url_expires_at->toIso8601String(),
                ])
                ->values()
                ->all(),
            'deletion_certificate' => $certificate === null ? null : [
                'issued_at' => $certificate->issued_at->toIso8601String(),
                'summary' => $certificate->summary,
                'exceptions' => $certificate->exceptions,
            ],
        ];
    }
}
