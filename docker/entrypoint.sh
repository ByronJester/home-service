#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
    export DB_URL="$DATABASE_URL"
fi

if [ -z "${DB_CONNECTION:-}" ]; then
    case "${DB_URL:-}" in
        postgres:*|postgresql:*)
            export DB_CONNECTION=pgsql
            ;;
        mysql:*)
            export DB_CONNECTION=mysql
            ;;
    esac
fi

if [ -z "${DB_CONNECTION:-}" ] && [ -z "${DB_URL:-}" ]; then
    echo "No database is configured. Create a Render Postgres database and link DATABASE_URL, or set DB_CONNECTION." >&2
fi

export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

php artisan storage:link --force >/dev/null 2>&1 || true

attempt=1
until php artisan migrate --force; do
    if [ "$attempt" -ge 10 ]; then
        echo "Migrations failed after ${attempt} attempts." >&2
        exit 1
    fi

    echo "Database is not ready. Retrying migrations (${attempt}/10)..."
    attempt=$((attempt + 1))
    sleep 3
done

php artisan db:seed --force
php artisan config:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

port="${PORT:-8000}"
sed -i "s/listen 8000;/listen ${port};/" /etc/nginx/conf.d/laravel.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
