<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Deliberately empty beyond a placeholder root route at Session 5.
| The public consent widget, DSAR portal, and admin SPA routes are
| implemented at Session 6 against the contract already validated in
| docs/architecture/openapi.yaml.
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'status' => 'Session 5 — environment baseline. No features implemented yet.',
    ]);
});
