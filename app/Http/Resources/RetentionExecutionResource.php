<?php

namespace App\Http\Resources;

use App\Models\RetentionExecution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.RetentionExecution in
// docs/architecture/openapi.yaml (B-05). certificate re-shapes to the
// existing DeletionCertificateSummary schema (already used by
// DsarStatus.deletion_certificate) rather than inventing a second
// certificate shape — null for a dry-run execution
// (RetentionExecutor::preview() never creates one); every real execution
// has exactly one, per the deletion_certificates_exactly_one_source DB
// constraint (Session 11).
/**
 * @mixin RetentionExecution
 */
class RetentionExecutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'retention_policy_id' => $this->retention_policy_id,
            'mode' => $this->mode,
            'affected_record_count' => $this->affected_record_count,
            'executed_at' => $this->executed_at,
            'certificate' => $this->whenLoaded('deletionCertificate', fn () => $this->deletionCertificate === null ? null : [
                'summary' => $this->deletionCertificate->summary,
                'exceptions' => $this->deletionCertificate->exceptions,
                'issued_at' => $this->deletionCertificate->issued_at,
            ]),
        ];
    }
}
