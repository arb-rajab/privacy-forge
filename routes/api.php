<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every endpoint here matches docs/architecture/openapi.yaml. Public
| routes (Consent tag) stay under the default stateless 'api' middleware
| group applied by bootstrap/app.php's withRouting() — appropriate for a
| widget embedded cross-origin on a third-party page. Admin routes
| additionally load the 'web' middleware group for session-cookie auth
| (staffAuth in the OpenAPI spec, per 05-api-contracts.md's
| "session-based auth for staff users") — there is no Sanctum dependency
| in composer.json, so this is the built-in-only way to get session auth
| on api.php-registered routes.
|
*/

use App\Http\Controllers\Admin\ConsentNoticeController;
use App\Http\Controllers\Admin\ConsentPurposeController;
use App\Http\Controllers\Admin\DataCategoryController;
use App\Http\Controllers\Admin\DsarController as AdminDsarController;
use App\Http\Controllers\Admin\DsarQueueController;
use App\Http\Controllers\Admin\PolicyController;
use App\Http\Controllers\Admin\RetentionPolicyController;
use App\Http\Controllers\ConnectorCallbackController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\DsarController;
use App\Http\Controllers\ExportBundleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/consent-purposes/{purposeId}/notice', [ConsentController::class, 'showNotice']);
    Route::post('/consent', [ConsentController::class, 'capture']);
    Route::post('/consent/{consentId}/withdraw', [ConsentController::class, 'withdraw']);

    Route::post('/dsar', [DsarController::class, 'submit']);
    // Named so DsarController::submit can build a signed URL to it
    // (URL::temporarySignedRoute) — the only route in this file that
    // needs a name for that reason.
    Route::get('/dsar/status/{signedToken}', [DsarController::class, 'status'])->name('dsar.status');
    Route::get('/dsar/export/{signedToken}/download', [ExportBundleController::class, 'download'])->name('dsar.export.download');
    Route::get('/dsar/export-bundle/{bundleId}/raw', [ExportBundleController::class, 'raw'])->name('dsar.export.raw');

    // Connector Callback tag (ADR-0004) — connector-authenticated via
    // X-Connector-Signature, a separate credential space from staff
    // sessions (connectorAuth in openapi.yaml), so this deliberately sits
    // outside the ['web', 'auth'] admin group below.
    Route::post('/connector-callback/{taskId}', [ConnectorCallbackController::class, 'handle']);

    Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
        Route::post('/consent-purposes', [ConsentPurposeController::class, 'store']);
        Route::delete('/consent-purposes/{purposeId}', [ConsentPurposeController::class, 'destroy']);
        Route::post('/consent-purposes/{purposeId}/notices', [ConsentNoticeController::class, 'store']);
        Route::post('/dsar/{dsarId}/verify-identity', [AdminDsarController::class, 'verifyIdentity']);
        Route::post('/dsar/{dsarId}/approve-erasure', [AdminDsarController::class, 'approveErasure']);

        // DSAR queue visibility (Session 10) — read-only, available to any
        // staff session per the roles matrix (Support Staff "can view DSAR
        // status"; not an ADR-0001 sensitive action, so no PolicyEvaluator
        // gate, matching the plain-auth pattern already used above for
        // consent-purpose management.
        Route::get('/dsar', [DsarQueueController::class, 'index']);

        // policy.update (ADR-0006, R-03 — closed this session). Every
        // route below is gated by the same PolicyEvaluator call inside
        // PolicyController, Owner-only.
        Route::get('/policies', [PolicyController::class, 'index']);
        Route::get('/policies/{policyId}', [PolicyController::class, 'show']);
        Route::patch('/policies/{policyId}', [PolicyController::class, 'update']);

        // retention.policy.manage (Session 11, ADR-0002/US-010/US-011) —
        // the fourth registered sensitive action. Every route below,
        // including the dry-run preview, is gated by the same
        // PolicyEvaluator call (Owner or Privacy Manager).
        Route::get('/data-categories', [DataCategoryController::class, 'index']);
        Route::post('/data-categories', [DataCategoryController::class, 'store']);
        Route::get('/retention-policies', [RetentionPolicyController::class, 'index']);
        Route::post('/retention-policies', [RetentionPolicyController::class, 'store']);
        Route::get('/retention-policies/{policyId}', [RetentionPolicyController::class, 'show']);
        Route::patch('/retention-policies/{policyId}', [RetentionPolicyController::class, 'update']);
        Route::post('/retention-policies/{policyId}/dry-run', [RetentionPolicyController::class, 'dryRun']);
    });
});
