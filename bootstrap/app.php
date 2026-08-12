<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The ABAC PolicyEvaluator (ADR-0001) is invoked explicitly inside
        // sensitive-action controllers, not registered here as blanket
        // middleware — see docs/adr/ADR-0001-abac-policy-model.md for why
        // that boundary matters (a middleware-only gate would be easy to
        // forget to attach to a new route; an explicit call in the
        // controller is harder to silently omit during review).
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
