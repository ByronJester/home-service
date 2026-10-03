# Render web service. Listens on $PORT.
# On each start the container runs migrations and database seeders.
#
# Required environment:
#   APP_KEY, APP_ENV=production, APP_DEBUG=false, APP_URL
#   DATABASE_URL from a linked Render Postgres database, or DB_CONNECTION plus DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
#   CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET

FROM node:22-bookworm-slim AS node

FROM composer:2 AS composer

FROM php:8.3-cli-bookworm AS build

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:nmjje4gNq2/L9bhvW4NPq3bRiexduTWKGUJTsY4pI2I= \
    APP_URL=http://localhost \
    DB_CONNECTION=sqlite \
    SESSION_DRIVER=array \
    CACHE_STORE=array \
    QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-scripts --no-interaction \
    && cp .env.example .env \
    && php artisan key:generate --force --ansi \
    && php artisan package:discover --ansi \
    && npm ci \
    && npm run build \
    && rm -rf node_modules .env

FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql gd zip bcmath opcache \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx.conf /etc/nginx/conf.d/laravel.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY --from=build /app /var/www/html

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD sh -c 'curl -fsS "http://127.0.0.1:${PORT:-8000}/up" >/dev/null'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
