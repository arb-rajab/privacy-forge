<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\DsarRequest;
use App\Models\ExportBundle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// US-008/FR-010: assembles the export bundle once every connector export
// task has succeeded (DsarCompletionEvaluator). No real connector exists
// in v1 (FR-019), so the assembled content is what this instance itself
// holds on the subject — consent records keyed by the same
// subject_identifier_hash already used for the NFR-006 rate-limit lookup
// — rather than data fetched from a third-party system. A real connector
// would contribute its own rows to this same bundle in a future session.
class ExportBundleAssembler
{
    public function assemble(DsarRequest $dsar): void
    {
        $consentRecords = ConsentRecord::query()
            ->where('subject_identifier_hash', $dsar->subject_identifier_hash)
            ->get(['purpose_id', 'notice_id', 'status', 'given_at', 'withdrawn_at']);

        $ttlHours = (int) config('connectors.export_bundle_ttl_hours');
        $expiresAt = now()->addHours($ttlHours);

        $jsonPath = $this->store($dsar, 'json', $this->toJson($dsar, $consentRecords));
        $csvPath = $this->store($dsar, 'csv', $this->toCsv($consentRecords));

        foreach ([['json', $jsonPath], ['csv', $csvPath]] as [$format, $path]) {
            ExportBundle::create([
                'dsar_request_id' => $dsar->id,
                'download_token' => Str::random(64),
                'storage_path' => $path,
                'format' => $format,
                'signed_url_expires_at' => $expiresAt,
            ]);
        }
    }

    // FR-010/data classification (04-data-model.md): "encrypted at rest".
    // Encrypted at the application layer (APP_KEY-derived, same primitive
    // as DsarRequest::subject_identifier) before it ever reaches object
    // storage, rather than relying on bucket-level SSE configuration —
    // this way the guarantee holds regardless of how a given deployment's
    // bucket is configured. ExportBundleController::raw() decrypts on the
    // way back out.
    private function store(DsarRequest $dsar, string $extension, string $contents): string
    {
        $path = "exports/{$dsar->id}/bundle.{$extension}";
        Storage::disk('s3')->put($path, Crypt::encryptString($contents));

        return $path;
    }

    /**
     * @param  Collection<int, ConsentRecord>  $consentRecords
     */
    private function toJson(DsarRequest $dsar, Collection $consentRecords): string
    {
        $payload = [
            'dsar_id' => $dsar->id,
            'request_type' => $dsar->request_type,
            'generated_at' => now()->toIso8601String(),
            'consent_records' => $consentRecords->map(fn (ConsentRecord $record): array => [
                'purpose_id' => $record->purpose_id,
                'notice_id' => $record->notice_id,
                'status' => $record->status,
                'given_at' => $record->given_at->toIso8601String(),
                'withdrawn_at' => $record->withdrawn_at?->toIso8601String(),
            ])->all(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  Collection<int, ConsentRecord>  $consentRecords
     */
    private function toCsv(Collection $consentRecords): string
    {
        $rows = ['purpose_id,notice_id,status,given_at,withdrawn_at'];

        foreach ($consentRecords as $record) {
            $rows[] = implode(',', [
                $record->purpose_id,
                $record->notice_id,
                $record->status,
                $record->given_at->toIso8601String(),
                $record->withdrawn_at?->toIso8601String(),
            ]);
        }

        return implode("\n", $rows);
    }
}
