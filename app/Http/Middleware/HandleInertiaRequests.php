<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Deliberately empty at Session 5 — staff role, ABAC-derived UI
     * capabilities, and demo-mode flags (per the Demo Instance Data Safety
     * design in 06-security-threat-model.md) get added here once the
     * auth/ABAC layer exists, at Session 6.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
        ];
    }
}
