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
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=frontend /app/public/build public/build/

RUN php artisan storage:link \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod +x docker/entrypoint.sh

ENV OCTANE_SERVER=frankenphp \
    APP_ENV=production \
    APP_DEBUG=false

# 8080 is the default when no PORT env var is injected (Fly.io). Render.com
# and similar platforms set PORT themselves; entrypoint.sh reads it at
# runtime, so nothing here needs to change between platforms.
EXPOSE 8080

ENTRYPOINT ["docker/entrypoint.sh"]
