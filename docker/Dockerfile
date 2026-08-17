# privacy-forge — application container (development)
#
# This image runs `php artisan serve`, which is appropriate for local
# development and for the CI environment. The production reference
# deployment (PHP-FPM + a real web server, built at Session 8) is a
# separate, hardened image — this Dockerfile is deliberately not that,
# and should not be mistaken for a production artifact.

FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        curl \
        ca-certificates \
        gnupg \
    && docker-php-ext-install pdo pdo_pgsql zip sockets pcntl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
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

# R-07 (docs/project-memory/10-risk-register.md): this layer — a real
# Chromium download for Pest's Browser Testing plugin (pestphp/pest-
# plugin-browser) — is the single most expensive step in the whole build
# (~15-20 minutes on a cold cache). It depends only on package.json/
# package-lock.json, never on application code, so it's deliberately
# copied and run *before* `COPY . .` below: an ordinary code change no
# longer invalidates this layer and forces a multi-minute re-download,
# which is what happened when this was ordered after `COPY . .`.
# `--with-deps` pulls the OS-level libraries a real headless Chromium
# needs on Debian, matching Playwright's own documented install step.
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund \
    && npx playwright install --with-deps chromium

COPY . .

RUN composer install --no-interaction --no-progress

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
