# Dev stage: PHP-FPM + Composer + Node + common PHP extensions.
# The scheduler/queue/nginx services share this image.
# PHP must satisfy composer.lock as installed on the host (vendor/ is mounted),
# so this tracks the host's PHP major.minor.

FROM php:8.5-fpm AS base

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
    && docker-php-ext-install -j$(nproc) gd pdo_mysql bcmath zip pcntl \
    && printf '\n' | pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Large document uploads: raise PHP's body and memory limits
# (the app's DOCUMENTS_MAX_SIZE_KB must stay within these).
RUN printf 'upload_max_filesize=128M\npost_max_size=128M\nmemory_limit=512M\n' > "$PHP_INI_DIR/conf.d/99-agent-finance.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 22 for the frontend build (used by `php artisan dev` flows too).
# Must be the Debian (glibc) build matching this image's distro — the alpine
# binary is musl-linked and will not execute here. npm/npx are linked from
# /usr/local/bin as in the official node image; without those symlinks `npm`
# resolves to the libexec shell shim, which looks for npm under
# dirname(process.execPath) and fails.
COPY --from=node:22-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -sf ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
ENV PATH="/usr/local/bin:${PATH}"

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
