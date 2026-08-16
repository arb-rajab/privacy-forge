<?php

use App\Models\User;

// R-05 (10-risk-register.md) — DsarLifecycleTest.php proves the real
// POST /login endpoint drives the admin half of the DSAR lifecycle, but
// it calls that endpoint directly (postJson), never Login.vue's own
// form. This test is the complement: a real browser actually typing
// into and submitting the login page itself, and using the logout link
// Welcome.vue now renders for an authenticated session.
test('a staff user can log in and log out through the real login page in a browser', function () {
    $owner = User::factory()->owner()->create(['email' => 'browser-owner@example.test']);

    visit('/login')
        ->assertSee('Staff login')
        ->type('#email', 'browser-owner@example.test')
        ->type('#password', 'password')
        ->click('Log in')
        ->assertPathIs('/')
        ->assertSee('Logged in as')
        ->assertSee('owner')
        ->click('Log out')
        ->assertPathIs('/login');
});

test('an unknown login shows a generic, non-revealing error in the browser', function () {
    visit('/login')
        ->type('#email', 'nobody@example.test')
        ->type('#password', 'whatever')
        ->click('Log in')
        ->assertSee('do not match our records');
});
