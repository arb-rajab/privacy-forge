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
     * `auth.user` (R-05, 10-risk-register.md): every Inertia page can now
     * render a real logged-in/logged-out state instead of assuming one.
     * Only the fields a page might reasonably show are exposed — not the
     * full model (password/remember_token are already $hidden on User
     * regardless, but this stays an explicit allow-list rather than
     * relying on that alone).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
            ],
            // Every page's own fetch()-based POSTs (login, logout, and
            // any future admin action) need this — see Login.vue and
            // Welcome.vue. This project doesn't route requests through
            // Inertia's own axios instance, which would attach the
            // equivalent XSRF-TOKEN cookie header automatically.
            'csrfToken' => csrf_token(),
            // Demo Instance Data Safety, control 4 (06-security-threat-
            // model.md): a visible warning banner. Shared globally, not
            // per-page, so a future page never has to remember to check
            // this itself — see Welcome.vue for the banner this drives.
            'demoMode' => config('demo.enabled'),
        ];
    }
}
