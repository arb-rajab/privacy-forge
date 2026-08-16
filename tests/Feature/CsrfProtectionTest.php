<?php

use App\Models\PolicyDefinition;
use App\Models\User;
use Illuminate\Testing\TestResponse;

// T-12 in 06-security-threat-model.md, addressed via R-05
// (10-risk-register.md). Laravel's own CSRF middleware
// (Illuminate\Foundation\Http\Middleware\VerifyCsrfToken, aliased
// ValidateCsrfToken in this Laravel version) has a built-in escape
// hatch: runningUnitTests() skips verification entirely whenever
// app()->runningUnitTests() is true — true for every ordinary Pest
// feature test, including every actingAs()->postJson() call elsewhere in
// this suite. Simply asserting "the admin route works" therefore proves
// nothing about CSRF being enforced for real. This file deliberately
// defeats that escape hatch (by rebinding the container's 'env' value
// away from 'testing') so the actual tokensMatch()/getTokenFromRequest()
// logic runs against real requests on the real admin route group,
// instead of assuming the framework default is doing its job untested.

function bypassCsrfTestingShortcut(): void
{
    app()->instance('env', 'production');
}

function sessionCookieFrom(TestResponse $response): ?string
{
    $name = config('session.cookie');

    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $name) {
            return $cookie->getValue();
        }
    }

    return null;
}

test('a state-changing admin request without a CSRF token is rejected, even from an authenticated session', function () {
    bypassCsrfTestingShortcut();

    $owner = User::factory()->owner()->create();
    $policy = PolicyDefinition::factory()->forPolicyUpdate()->create();

    // actingAs() authenticates the guard directly, independent of session
    // cookies — this proves CSRF rejection is not merely "you weren't
    // logged in": the request is genuinely authenticated and still
    // rejected, because it carries no CSRF token at all.
    $this->actingAs($owner)
        ->patchJson("/api/v1/admin/policies/{$policy->id}", [
            'effect' => 'allow',
            'subject_conditions' => ['role' => ['in' => ['owner']]],
        ])
        ->assertStatus(419);
});

test('a state-changing admin request with a valid CSRF token succeeds', function () {
    bypassCsrfTestingShortcut();

    $owner = User::factory()->owner()->create();
    $policy = PolicyDefinition::factory()->forPolicyUpdate()->create();
    $sessionCookieName = config('session.cookie');

    // Establish a real session first (a plain GET), capture its actual
    // CSRF token and session cookie, then forward both on the follow-up
    // state-changing request — exactly what a real browser does
    // automatically via the XSRF-TOKEN cookie, done by hand here since
    // the point is proving the mechanism itself works.
    $bootstrap = $this->actingAs($owner)->getJson('/api/v1/admin/policies');
    $bootstrap->assertOk();

    $token = session()->token();
    $sessionId = sessionCookieFrom($bootstrap);
    expect($token)->not->toBeNull();
    expect($sessionId)->not->toBeNull();

    $this->actingAs($owner)
        ->withUnencryptedCookie($sessionCookieName, $sessionId)
        ->withHeaders(['X-CSRF-TOKEN' => $token])
        ->patchJson("/api/v1/admin/policies/{$policy->id}", [
            'effect' => 'allow',
            'subject_conditions' => ['role' => ['in' => ['owner']]],
        ])
        ->assertStatus(200);
});
