#!/bin/sh
set -e

cd /var/www

# First boot: install dependencies and prepare the app.
if [ ! -d vendor ] || [ ! -f .env ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    composer install --no-interaction --no-progress

    if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
        php artisan key:generate --force
    fi
fi

# Wait for the database, then migrate + seed (idempotent).
echo "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until php -r 'try { new PDO(sprintf("mysql:host=%s;port=%s", getenv("DB_HOST") ?: "mysql", getenv("DB_PORT") ?: "3306"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
    sleep 2
done

php artisan migrate --force
php artisan db:seed --force

exec "$@"
