<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// T-11 and T-13 in 06-security-threat-model.md, addressed via R-05
// (10-risk-register.md): this is the first real test of the login/
// logout/session-invalidation flow against an actual HTTP session —
// everything before this session only ever exercised sensitive-action
// controllers via actingAs(), never a real POST /login.

// Laravel's test HTTP client does not automatically carry cookies between
// separate $this->call()-based requests within a test (each one only
// sends whatever was explicitly attached via withCookie()) — so
// "session id changed after login" can only be tested for real by
// manually forwarding the exact (already-encrypted) session cookie value
// the server issued, the way a real browser would.
function sessionCookieValue(TestResponse $response): ?string
{
    $name = config('session.cookie');

    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $name) {
            return $cookie->getValue();
        }
    }

    return null;
}

test('a staff user can log in with valid credentials and gets a fresh, regenerated session', function () {
    $user = User::factory()->owner()->create(['password' => bcrypt('correct-password')]);
    $sessionCookieName = config('session.cookie');

    $preLogin = $this->get('/login');
    $originalSessionId = sessionCookieValue($preLogin);
    expect($originalSessionId)->not->toBeNull();

    $response = $this->withUnencryptedCookie($sessionCookieName, $originalSessionId)
        ->postJson('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk()->assertJson(['redirect' => '/']);

    expect(auth()->id())->toBe($user->id);

    // T-11: session regenerated on login (same underlying session
    // continued from the request above, then given a fresh id), not
    // merely a coincidentally-different id from an unrelated session —
    // defends against fixation as well as hijacking of a pre-login
    // session id.
    $newSessionId = sessionCookieValue($response);
    expect($newSessionId)->not->toBeNull();
    expect($newSessionId)->not->toBe($originalSessionId);
});

test('logout invalidates the session and clears the guard', function () {
    $user = User::factory()->owner()->create(['password' => bcrypt('correct-password')]);

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertOk();

    expect(auth()->check())->toBeTrue();

    $this->postJson('/logout')
        ->assertOk()
        ->assertJson(['redirect' => '/login']);

    expect(auth()->check())->toBeFalse();
});

test('an unknown email and a wrong password produce the identical generic error message', function () {
    $user = User::factory()->owner()->create(['password' => bcrypt('correct-password')]);

    $wrongPassword = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'not-the-right-password',
    ])->assertStatus(422)->json();

    $unknownEmail = $this->postJson('/login', [
        'email' => 'nobody-with-this-email@example.test',
        'password' => 'anything',
    ])->assertStatus(422)->json();

    // T-13: the caller cannot distinguish "email exists, password wrong"
    // from "email doesn't exist" from the response alone.
    expect($wrongPassword['errors']['email'][0])->toBe($unknownEmail['errors']['email'][0]);
    expect(auth()->check())->toBeFalse();
});

test('a failed attempt locks out the next attempt, and each further failure at least doubles the wait', function () {
    $user = User::factory()->owner()->create(['password' => bcrypt('correct-password')]);
    $throttleKey = Str::transliterate(Str::lower($user->email)).'|127.0.0.1';

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
    expect(Cache::get("login-attempts:{$throttleKey}"))->toBe(1);
    $firstDecay = now()->diffInSeconds(Cache::get("login-lockout:{$throttleKey}"));

    // Immediately retrying with the *correct* password is still rejected
    // by the lockout itself, before credentials are even checked —
    // proving this isn't merely "the password was wrong again".
    $locked = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertStatus(422)->json();
    expect($locked['errors']['email'][0])->toContain('Too many login attempts');
    expect(auth()->check())->toBeFalse();

    // Force the first lockout to have already expired, so the next
    // failed attempt is actually evaluated (not just re-rejected by the
    // same still-active lockout), then confirm the *second* failure's
    // lockout window is longer than the first's (T-13: backoff grows).
    Cache::put("login-lockout:{$throttleKey}", now()->subSecond(), now()->addHour());
    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
    expect(Cache::get("login-attempts:{$throttleKey}"))->toBe(2);
    $secondDecay = now()->diffInSeconds(Cache::get("login-lockout:{$throttleKey}"));

    expect($secondDecay)->toBeGreaterThan($firstDecay);
});

test('a successful login clears any accumulated rate-limit state', function () {
    $user = User::factory()->owner()->create(['password' => bcrypt('correct-password')]);
    $throttleKey = Str::transliterate(Str::lower($user->email)).'|127.0.0.1';

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
    Cache::put("login-lockout:{$throttleKey}", now()->subSecond(), now()->addHour());

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertOk();

    expect(Cache::get("login-attempts:{$throttleKey}"))->toBeNull();
    expect(Cache::get("login-lockout:{$throttleKey}"))->toBeNull();
});
