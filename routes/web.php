<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The embeddable consent widget (resources/js/widget/) is a standalone
| Vite build (public/widget.js), not a page route — see
| vite.widget.config.js and public/embed-example.html.
|
| The two DSAR portal routes below are pure UI shells around the
| unchanged JSON API (docs/architecture/openapi.yaml's DSAR Portal tag).
| /dsar/status/{signedToken} deliberately does not re-check the signature
| itself: it forwards signedToken and the current query string
| client-side straight to GET /api/v1/dsar/status/{signedToken}, so the
| exact signature minted for that API path is what gets validated —
| see DsarStatus.vue.
|
| Login/logout (R-05, 10-risk-register.md) live here rather than under
| /api/v1 — they're an Inertia page + session-bootstrap concern, not part
| of the versioned JSON API contract in docs/architecture/openapi.yaml.
| 'guest'/'auth' are Laravel's own default middleware aliases ('auth' is
| already used on the admin group in routes/api.php).
|
*/

Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'status' => 'Session 13 — consent widget and DSAR portal UI live.',
    ]);
});

Route::get('/dsar', function () {
    return Inertia::render('DsarSubmit');
});

Route::get('/dsar/status/{signedToken}', function (string $signedToken) {
    return Inertia::render('DsarStatus', ['signedToken' => $signedToken]);
});

// Admin DSAR queue (this session) — a UI shell around the unchanged
// Admin\DsarQueueController / Admin\DsarController JSON API
// (docs/architecture/openapi.yaml's "Admin — DSAR Queue" /
// "Admin — Purposes and Policies" tags), matching the DSAR portal
// routes' own shape above. 'auth' redirects an unauthenticated visitor
// to /login rather than rendering a page whose first fetch() would just
// 401 (DsarQueueController::index() already aborts 401 as a backstop,
// but a signed-out visitor should never reach the page at all).
Route::get('/admin/dsar', function () {
    return Inertia::render('AdminDsarQueue');
})->middleware('auth');

// Retention policy management UI (Session 21) — a UI shell around the
// unchanged Admin\DataCategoryController / Admin\RetentionPolicyController
// JSON API (docs/architecture/openapi.yaml's "Admin — Purposes and
// Policies" tag). No new endpoints: this page only calls
// GET/POST /api/v1/admin/data-categories, GET/POST/PATCH
// /api/v1/admin/retention-policies, and POST .../dry-run — see
// AdminRetention.vue for how it keeps the dry-run/real-execution
// distinction ADR-0002 exists for unambiguous in the UI itself.
Route::get('/admin/retention', function () {
    return Inertia::render('AdminRetention');
})->middleware('auth');

// RoPA export UI (Session 21) — a single page around the unchanged
// GET /api/v1/admin/ropa/export?format=csv|pdf endpoint (US-013/FR-016).
Route::get('/admin/ropa', function () {
    return Inertia::render('AdminRopa');
})->middleware('auth');

// ABAC policy definition view/edit UI (Session 21, stretch) — a UI shell
// around the unchanged Admin\PolicyController JSON API (policy.update,
// ADR-0006). Owner-only at the API layer; this page renders for any
// authenticated staff user and surfaces the ABAC denial inline, matching
// every other admin page's convention of not duplicating server-side
// authorisation in the client.
Route::get('/admin/policies', function () {
    return Inertia::render('AdminPolicies');
})->middleware('auth');

// Audit log query view (B-04, this session) — a UI shell around the new
// GET /api/v1/admin/audit-log endpoint (Admin\AuditLogController,
// audit.log.view). See AdminAuditLog.vue for why this page never applies
// its own row-level scoping client-side — that's a server decision.
Route::get('/admin/audit-log', function () {
    return Inertia::render('AdminAuditLog');
})->middleware('auth');
