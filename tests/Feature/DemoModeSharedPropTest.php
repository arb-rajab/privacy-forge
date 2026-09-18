<?php

// Demo Instance Data Safety, control 4 (docs/project-memory/
// 06-security-threat-model.md) — the warning banner Welcome.vue renders
// depends on this shared prop actually reflecting config('demo.enabled')
// on every page load, not just existing in the middleware source.
//
// The only Feature test that renders a real Inertia page (every other
// Feature test hits routes/api.php's JSON endpoints, which never touch
// the Blade root view or @vite()). The php-quality CI job never builds
// frontend assets — only e2e does, because Browser tests run against a
// real browser that genuinely needs the built JS/CSS — so without
// withoutVite() here, @vite() throws on a missing public/build/
// manifest.json and this fails with "Not a valid Inertia response"
// instead of testing what it's meant to.
beforeEach(fn () => $this->withoutVite());

test('demoMode is shared as false by default, matching .env.example\'s documented default', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('demoMode', false));
});

test('demoMode reflects config(\'demo.enabled\') when the demo instance flag is on', function () {
    config(['demo.enabled' => true]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('demoMode', true));
});
