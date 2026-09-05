# Local development image: plain PHP CLI server + Vite, backed by the
# mysql/redis services in docker-compose.yml. Kept separate from the
# production Dockerfile (docker/Dockerfile) on purpose — dev needs Xdebug,
# composer, and node; production doesn't.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libonig-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip pcntl intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

EXPOSE 8000 5173

CMD ["sh", "-c", "composer install && php artisan serve --host=0.0.0.0 --port=8000"]
