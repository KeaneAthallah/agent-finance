# Dev stage: PHP-FPM + Composer + Node + common PHP extensions.
# The scheduler/queue/nginx services share this image.

FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        zip \
        unzip \
        libxml2-dev \
        libonig-dev \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-ind \
        tesseract-ocr-eng \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql bcmath zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 22 for the frontend build (used by `php artisan dev` flows too).
COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-alpine /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
ENV PATH="/usr/local/lib/node_modules/npm/bin:/usr/local/bin:${PATH}"

WORKDIR /var/www

# ---- dev stage: source mounted, deps installed at runtime ----
FROM base AS dev

# Install PHP deps from lockfile if vendor/ is not mounted.
RUN composer install --no-dev --no-scripts --no-progress 2>/dev/null || true

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- production stage (Phase 10 hardening; provided now for completeness) ----
FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-progress --optimize-autoloader

COPY . .
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules \
    && php artisan config:cache --no-ansi 2>/dev/null || true

RUN chown -R www-data:www-data storage bootstrap/cache \
    && cp docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
