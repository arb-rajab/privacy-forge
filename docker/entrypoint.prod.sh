#!/bin/sh
set -eu

# B-06 production entrypoint. Config/view caching happens here, at
# container start, not at image build time — config:cache bakes the
# *current* environment's values into a compiled file, and this image is
# generic across deployments (dev/demo/self-hosted), each with different
# env vars. Caching at build time would bake in whatever values happened
# to be present in the build environment, which is wrong for every
# deployment that isn't that exact build.
#
# route:cache is deliberately NOT run: routes/web.php's Inertia page
# routes are closure-based (`Route::get('/', function () {...})`), and
# Laravel's route cache cannot serialize closures — it throws
# `LogicException: Unable to prepare route [...] for serialization. Uses
# Closure.` Running it here would crash-loop every container start, not
# just skip an optimization. Confirmed by inspecting routes/web.php
# before writing this script, not assumed.
php artisan config:cache
php artisan view:cache

exec "$@"
