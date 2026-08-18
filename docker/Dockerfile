# privacy-forge — application container (development)
#
# This image runs `php artisan serve`, which is appropriate for local
# development and for the CI environment. The production reference
# deployment (PHP-FPM + a real web server) is a separate, hardened image
# — docker/Dockerfile.prod (`app`/`web` targets, paired with
# docker-compose.prod.yml) — built for real at Deployment Session A
# (docs/project-memory/08-deployment-and-operations.md), not at Session
# 8 as this comment previously and incorrectly claimed. See
# docs/project-memory/09-decision-log.md's Session 22/Deployment
# Session A entries for the full account of that drift. This Dockerfile
# is deliberately not that, and should not be mistaken for a production
# artifact.
#
# Multi-stage, two usable final targets (R-07,
# docs/project-memory/10-risk-register.md, Session 18):
#   - `runtime` — what docker-compose.yml's `app`/`worker` services build
#     and run by default (`docker compose up --build`). Contains no
#     Node.js, no npm, no Playwright, no Chromium, and none of the OS
#     packages `playwright install --with-deps` pulls in — this is the
#     actual multi-minute cost R-07 was about, and none of it is ever
#     used by application code at runtime, only by pestphp/pest-plugin-
#     browser's tests/Browser/ suite.
#   - `test` — extends `deps` (the same PHP dependency layer `runtime`
#     is built from, so both share a cache up to that point) with
#     Node.js, npm, and Playwright/Chromium — used only by the
#     `app-e2e` Compose service (`docker compose --profile e2e run --rm
#     app-e2e composer test:e2e`).
# A stranger running `docker compose up` for the first time never pays
# for the `test` stage's Node/Playwright/Chromium download.
#
# `sockets`/`pcntl` stay in the shared `deps` stage below, not in
# `test`-only: this session first tried isolating them to `test` too
# (they're only ever *used* by tests/Browser/, per decision log Session
# 13) and that broke `composer test` — the plain Feature suite, not just
# the browser suite — with `Call to undefined function
# Pest\Browser\Support\socket_create_listen()`. pestphp/pest-plugin-
# browser hooks into Pest's global test lifecycle at boot, regardless of
# which test file runs, so the extension has to be present for *any*
# `pest` invocation once the package is installed (it's a require-dev
# dependency either way). Compiling these two extensions costs real time
# — measured directly at ~73s, not the few seconds this comment
# originally assumed before actually timing it — but it's still an order
# of magnitude below the multi-minute Node/Playwright/Chromium cost this
# split exists to remove, so keeping them in `runtime` is the right
# trade. Confirmed by actually running `composer test` against the split
# image (165/165 passing), not assumed.

FROM php:8.3-cli AS deps

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        curl \
        ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql zip sockets pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./

# No composer.lock exists yet in this repository (Session 5 has not run a
# real `composer install` — see the handoff's stated limitation). This
# COPY + install step will generate one on first real build, on a machine
# with actual internet access to Packagist. It must not silently swallow
# failures with `|| true` — a failed install should fail the build, not be
# hidden, matching the fail-closed stance taken throughout this project's
# architecture (ADR-0006).
#
# Note: docker-compose.yml bind-mounts the repo over /var/www/html for
# live-reload during development, which would otherwise shadow this
# image's installed vendor/ directory with the host's (vendor-less)
# checkout. See the anonymous `/var/www/html/vendor` volume on the app
# and worker services — that's what prevents this COPY's work from being
# hidden at container start.
RUN composer install --no-interaction --no-scripts --no-progress

# ---------------------------------------------------------------------------
FROM deps AS runtime

COPY . .

RUN composer install --no-interaction --no-progress

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ---------------------------------------------------------------------------
FROM deps AS test-deps

# Node/Playwright are needed only by pestphp/pest-plugin-browser
# (tests/Browser/), never by application code at runtime — see decision
# log, Session 13. `gnupg` is needed only to import Nodesource's signing
# key for this same reason.
RUN apt-get update && apt-get install -y --no-install-recommends gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# R-07 (docs/project-memory/10-risk-register.md): this layer — a real
# Chromium download for Pest's Browser Testing plugin (pestphp/pest-
# plugin-browser) — is the single most expensive step in the `test`
# target's build (~7-18 minutes depending on network conditions). It
# depends only on package.json/package-lock.json, never on application
# code, so it's deliberately copied and run *before* `COPY . .` below: an
# ordinary code change no longer invalidates this layer and forces a
# multi-minute re-download. `--with-deps` pulls the OS-level libraries a
# real headless Chromium needs on Debian, matching Playwright's own
# documented install step.
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund \
    && npx playwright install --with-deps chromium

# ---------------------------------------------------------------------------
FROM test-deps AS test

COPY . .

RUN composer install --no-interaction --no-progress

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
