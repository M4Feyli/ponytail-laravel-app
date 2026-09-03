# Production image for Fly.io: Laravel Octane on FrankenPHP, single process,
# single container. No nginx, no php-fpm, no supervisor — FrankenPHP is its
# own HTTP server, which is the whole point of running Octane on it.

# ---- Stage 1: PHP dependencies -------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
# composer.lock* (glob) so the build still works before the first `composer
# install` has ever generated one — see README "run composer install first".
COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- Stage 2: frontend assets ---------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY resources/ resources/
COPY public/ public/
COPY vite.config.js ./
RUN npm run build

# ---- Stage 3: runtime -------------------------------------------------
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        redis \
        zip \
        intl \
        opcache \
    # FrankenPHP ships with Linux capabilities (setcap) on its binary so it
    # can bind privileged ports as non-root. Sandboxed platforms like
    # Render.com refuse to exec a binary carrying capabilities at all,
    # which surfaces as "operation not permitted" with no other detail —
    # strip them since we never bind a privileged port anyway.
    && apt-get update \
    && apt-get install -y --no-install-recommends libcap2-bin \
    && setcap -r /usr/local/bin/frankenphp \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

# FrankenPHP's worker is currently dying before it reaches
# frankenphp_handle_request() with no visible reason in the platform log —
# that almost always means a PHP fatal error is happening but going to a
# sink the log stream isn't capturing (production php.ini defaults to
# display_errors=Off, and error_log isn't pinned to stderr). Force both
# so the *next* crash actually tells us what broke.
RUN { \
        echo 'display_errors = On'; \
        echo 'log_errors = On'; \
        echo 'error_log = /dev/stderr'; \
    } > /usr/local/etc/php/conf.d/zz-app-error-logging.ini

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=frontend /app/public/build public/build/

RUN php artisan storage:link \
    # Pre-install the FrankenPHP worker script at build time (as root) —
    # Octane otherwise copies it into public/ on first boot (only if
    # missing), which fails once the container runs as non-root below.
    && cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php \
    && mkdir -p /config/caddy /data/caddy \
    && chown -R www-data:www-data /app /config/caddy /data/caddy \
    && chmod +x docker/entrypoint.sh

ENV OCTANE_SERVER=frankenphp \
    APP_ENV=production \
    APP_DEBUG=false

# 8080 is the default when no PORT env var is injected (Fly.io). Render.com
# and similar platforms set PORT themselves; entrypoint.sh reads it at
# runtime, so nothing here needs to change between platforms.
EXPOSE 8080

# Run as non-root: matches the capabilities stripped above and is what
# sandboxed platforms (Render) expect anyway.
USER www-data

ENTRYPOINT ["docker/entrypoint.sh"]
