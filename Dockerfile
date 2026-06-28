# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────────────────────
# Stage 1 — build: PHP + Composer + Node in one stage.
# The Wayfinder Vite plugin shells out to `php artisan wayfinder:generate` during
# `npm run build`, so the asset build needs PHP *and* the (non-dev) vendor present.
# That's why this is a combined stage rather than separate node/composer stages.
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2 AS build

WORKDIR /app

RUN apk add --no-cache nodejs npm

# Install PHP deps first (cached until composer.json/lock change).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs --prefer-dist --no-progress

# Install JS deps next (cached until package manifests change).
COPY package.json package-lock.json ./
RUN npm ci

# Now the source, then finalize the autoloader and build the front-end.
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative
RUN npm run build   # runs `php artisan wayfinder:generate` (relative URLs) then `vite build`

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2 — runtime: FrankenPHP, classic request-per-process mode (no Octane).
# Caddy serves /app/public on plain HTTP :8080; TLS is terminated upstream
# (Cloudflare Tunnel). SQLite-only: pdo_sqlite, NOT pdo_pgsql.
# ─────────────────────────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.4 AS runtime

WORKDIR /app

# Runtime extensions: SQLite + opcache; curl + gmp for web-push (VAPID/HTTP),
# intl/zip are harmless base extras.
RUN install-php-extensions pdo_sqlite opcache intl zip curl gmp \
    && apt-get update && apt-get install -y --no-install-recommends gosu curl \
    && rm -rf /var/lib/apt/lists/*

# App source, then overlay the built vendor + compiled assets from the build stage.
COPY . .
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build

# Config + scripts.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/healthcheck.sh /usr/local/bin/healthcheck

# Writable dirs (incl. the SQLite parent), owned by the runtime user. The volume
# mounts over storage/app at run time; the entrypoint re-ensures ownership then.
RUN chmod +x /usr/local/bin/entrypoint /usr/local/bin/healthcheck \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
       storage/logs storage/app/database bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Build provenance (no .git at runtime).
ARG APP_VERSION=dev
ARG APP_COMMIT=unknown
ENV APP_VERSION=${APP_VERSION} APP_COMMIT=${APP_COMMIT}

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["/usr/local/bin/healthcheck"]

ENTRYPOINT ["/usr/local/bin/entrypoint"]
# Default (web) command; the entrypoint branches on APP_ROLE and falls back to this.
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
