<?php

// Demo Instance Data Safety, control 4 (docs/project-memory/
// 06-security-threat-model.md) — the warning banner Welcome.vue renders
// depends on this shared prop actually reflecting config('demo.enabled')
// on every page load, not just existing in the middleware source.

test('demoMode is shared as false by default, matching .env.example\'s documented default', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('demoMode', false));
});

test('demoMode reflects config(\'demo.enabled\') when the demo instance flag is on', function () {
    config(['demo.enabled' => true]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('demoMode', true));
});
