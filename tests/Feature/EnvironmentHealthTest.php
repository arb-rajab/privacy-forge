<?php

use function Pest\Laravel\get;

// This is the one test that belongs to Session 5. It verifies the
// *environment* (the app boots, routes resolve, Laravel's built-in health
// check responds) — it is deliberately not a feature test, because
// Session 5's ground rules exclude business-logic implementation. Session
// 6 onward should not need to touch this file; it exists to make Gate
// 5->6 ("CI is green on tests") true without smuggling feature work in
// under the banner of "just a smoke test."
test('the application boots and the health check responds', function () {
    get('/up')->assertStatus(200);
});
