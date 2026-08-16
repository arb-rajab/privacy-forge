<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

// R-05 (10-risk-register.md): the one place a real staff session is
// created or destroyed. T-11/T-12/T-13 in 06-security-threat-model.md
// are mitigations that only mean something once this controller exists
// to protect — session regeneration on login (T-11), rate-limited
// attempts with a generic error message (T-13). CSRF (T-12) is not
// handled here at all — it's the 'web' middleware group's
// ValidateCsrfToken, already applied to this route, per bootstrap/app.php.
//
// Rate limiting is implemented directly against the cache rather than via
// Illuminate\Support\Facades\RateLimiter: RateLimiter::hit()'s decay
// argument only takes effect on the *first* call for a given key (it
// stores both the hit counter and its lockout timer via cache add(),
// which is a no-op once the key already exists), so passing a growing
// decay on each subsequent failed attempt is silently ignored by that
// facade — it cannot express genuine per-attempt exponential backoff,
// only a single fixed window. Tracking the lockout deadline explicitly
// here avoids that trap.
class AuthenticatedSessionController extends Controller
{
    public function create(): InertiaResponse
    {
        // csrfToken is shared globally by HandleInertiaRequests, not
        // passed here — Login.vue reads it via usePage().
        return Inertia::render('Login');
    }

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);
        $lockedUntil = Cache::get("login-lockout:{$throttleKey}");

        if ($lockedUntil !== null && now()->lt($lockedUntil)) {
            $secondsRemaining = (int) ceil(now()->diffInSeconds($lockedUntil));

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$secondsRemaining} seconds.",
            ]);
        }

        // T-13: credentials that fail for either reason (unknown email or
        // wrong password) hit this exact same branch and the exact same
        // message — Auth::attempt() never tells the caller which one it
        // was.
        if (! Auth::attempt($credentials)) {
            $attempts = (int) Cache::get("login-attempts:{$throttleKey}", 0) + 1;
            Cache::put("login-attempts:{$throttleKey}", $attempts, now()->addHour());

            // Exponential backoff: each additional failed attempt at
            // least doubles how long the next one must wait (2s, 4s, 8s,
            // ...), capped at 5 minutes, rather than a single fixed delay.
            $decaySeconds = min(300, 2 ** $attempts);
            Cache::put("login-lockout:{$throttleKey}", now()->addSeconds($decaySeconds), now()->addHour());

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        Cache::forget("login-attempts:{$throttleKey}");
        Cache::forget("login-lockout:{$throttleKey}");

        // T-11: a fresh session id on every successful login, not merely a
        // fresh cookie — defends against session fixation as well as
        // hijacking of a pre-login session id.
        $request->session()->regenerate();

        return response()->json(['redirect' => '/']);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // T-11: full invalidation and token rotation on logout, not just
        // clearing the guard — a stolen pre-logout session id must not
        // remain usable afterward.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['redirect' => '/login']);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->string('email'))).'|'.$request->ip();
    }
}
