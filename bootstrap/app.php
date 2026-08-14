<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // RFC 9457 Problem Details for every API error response, matching
        // components.schemas.ProblemDetail in docs/architecture/openapi.yaml
        // (05-api-contracts.md: "Error model"). Scoped to api/* requests
        // only — Inertia web routes keep Laravel's normal error handling.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => 404,
                $e instanceof AuthenticationException => 401,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $problem = [
                'type' => 'about:blank',
                'title' => class_basename($e) ?: 'Error',
                'status' => $status,
                'detail' => $e->getMessage(),
            ];

            if ($e instanceof ValidationException) {
                $problem['title'] = 'Validation failed';
                $problem['errors'] = $e->errors();
            }

            return response()->json($problem, $status);
        });
    })->create();
