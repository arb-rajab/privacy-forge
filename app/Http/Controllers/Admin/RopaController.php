<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PolicyEvaluator;
use App\Services\RopaGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

// US-013/FR-016 (Art. 30 RTM row). Staff-only (Admin — RoPA and Audit
// tag). ropa.export is a new sensitive action (the fifth registered one,
// after dsar.identity.verify, dsar.erasure.approve, policy.update, and
// retention.policy.manage) — gated by PolicyEvaluator, Owner or Privacy
// Manager, per the roles matrix ("Privacy Manager... view RoPA"; Support
// Staff explicitly "cannot... view retention policies or RoPA").
class RopaController extends Controller
{
    // Same "no single resource exists yet" sentinel DataCategoryController/
    // PolicyController's index() use — a RoPA export isn't a request
    // against any one row, so there is no natural resource id to evaluate
    // against.
    private const RESOURCE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private readonly PolicyEvaluator $policyEvaluator,
        private readonly RopaGenerator $generator,
    ) {}

    public function export(Request $request): Response|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'ropa.export',
            actor: $actor,
            resourceType: 'ropa',
            resourceId: self::RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Denied by ABAC policy evaluation',
                'status' => 403,
                'detail' => 'The ropa.export policy denied this request.',
                'policy_id' => $decision->policyId,
            ], 403);
        }

        $format = $request->query('format', 'pdf');
        abort_unless(in_array($format, ['pdf', 'csv'], true), 422, 'format must be one of: pdf, csv');

        $rows = $this->generator->generate();

        return $format === 'csv' ? $this->csvResponse($rows) : $this->pdfResponse($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function csvResponse(array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');

        // php://temp practically never fails to open; the explicit check
        // is what keeps phpstan's inference stable against fopen()'s
        // resource|false return type, the same pattern already used
        // throughout this codebase for FK-guaranteed-but-nullable relations
        // (e.g. DeletionCertificateGenerator::connectorName()).
        if ($handle === false) {
            throw new RuntimeException('Failed to open a temporary stream for RoPA CSV rendering.');
        }

        fputcsv($handle, [
            'purpose_name',
            'lawful_basis',
            'data_category',
            'data_category_description',
            'data_subjects_description',
            'retention_period_days',
            'post_expiry_action',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['purpose_name'],
                $row['lawful_basis'],
                $row['data_category_name'] ?? '',
                $row['data_category_description'] ?? '',
                $row['data_subjects_description'] ?? '',
                $row['retention_period_days'] ?? '',
                $row['post_expiry_action'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ropa-export.csv"',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function pdfResponse(array $rows): Response
    {
        $pdf = Pdf::loadView('ropa.export', ['rows' => $rows, 'generatedAt' => now()]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ropa-export.pdf"',
        ]);
    }
}
