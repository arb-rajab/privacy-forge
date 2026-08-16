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
