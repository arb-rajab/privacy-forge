<?php

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
*/

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
