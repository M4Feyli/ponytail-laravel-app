# Production image for Fly.io: Laravel Octane on FrankenPHP, single process,
# single container. No nginx, no php-fpm, no supervisor — FrankenPHP is its
# own HTTP server, which is the whole point of running Octane on it.

# ---- Stage 1: PHP dependencies -------------------------------------------
FROM php:8.4-cli AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
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

# log_errors + error_log=/dev/stderr get every PHP error into the platform
# log, which is what we actually need in production.
#
# display_errors must stay Off here, unlike a traditional php-fpm/Apache
# setup: FrankenPHP's classic php-server mode writes PHP's own stdout
# straight into the current HTTP response stream, so display_errors=On
# means any warning gets printed into the response body *before* Laravel's
# kernel calls $response->send(). That premature output makes the real
# sendHeaders() call fail with "headers already sent", which Laravel then
# converts into an ErrorException and tries to render/send *again* on the
# same already-sent response — a fatal cascade on every request that hits
# so much as a single notice. (We had this On temporarily to debug the
# worker-boot crash; the warning that started it all never showed up in
# logs because it went into the corrupted response body, not stderr.)
RUN { \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'error_log = /dev/stderr'; \
        # Laravel's own Octane docs pass this explicitly for FrankenPHP —
        # PHP's shipped php.ini-production drops "E" (environment) from
        # variables_order, so $_SERVER never gets populated from env vars.
        # FrankenPHP signals worker mode by setting FRANKENPHP_WORKER as an
        # env var, which the worker script reads via $_SERVER — without E
        # here it would never see it and would hit the same "must be in
        # worker mode" guard we're chasing, silently, since it's a plain
        # echo+exit(1), not a PHP error.
        echo 'variables_order = "EGPCS"'; \
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
    APP_DEBUG=false \
    # Caddy (which FrankenPHP embeds) defaults to $HOME/.config and
    # $HOME/.local/share for its own config/data storage. www-data has no
    # real home in this image, so without this it silently can't write
    # anywhere — which is very likely why the FrankenPHP app module fails
    # to start at all. Point it at the dirs we already created and chowned.
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

# 8080 is the default when no PORT env var is injected (Fly.io). Render.com
# and similar platforms set PORT themselves; entrypoint.sh reads it at
# runtime, so nothing here needs to change between platforms.
EXPOSE 8080

# Run as non-root: matches the capabilities stripped above and is what
# sandboxed platforms (Render) expect anyway.
USER www-data

ENTRYPOINT ["docker/entrypoint.sh"]
