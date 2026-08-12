<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Deliberately empty at Session 5. Every endpoint here must match
| docs/architecture/openapi.yaml exactly — implementation at Session 6
| should be written against that contract, not the other way around.
| The v1 prefix and route-file structure are established now so Session 6
| has a place to add routes without also deciding routing conventions.
|
*/

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Consent, DSAR, admin, and connector-callback routes land here at
    // Session 6, grouped and middleware-scoped per
    // docs/project-memory/05-api-contracts.md.
});
