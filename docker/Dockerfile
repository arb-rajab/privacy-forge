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
    && docker-php-ext-install pdo pdo_pgsql zip \
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
RUN composer install --no-interaction --no-scripts --no-progress

COPY . .

RUN composer install --no-interaction --no-progress

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
