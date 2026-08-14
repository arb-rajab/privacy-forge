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
use App\Http\Controllers\ConsentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/consent-purposes/{purposeId}/notice', [ConsentController::class, 'showNotice']);
    Route::post('/consent', [ConsentController::class, 'capture']);
    Route::post('/consent/{consentId}/withdraw', [ConsentController::class, 'withdraw']);

    Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
        Route::post('/consent-purposes', [ConsentPurposeController::class, 'store']);
        Route::delete('/consent-purposes/{purposeId}', [ConsentPurposeController::class, 'destroy']);
        Route::post('/consent-purposes/{purposeId}/notices', [ConsentNoticeController::class, 'store']);
    });
});
