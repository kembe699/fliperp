# Flip ERP — single image: Laravel API (PHP-FPM) + React frontend, behind nginx.
# Built by GitHub Actions (deploy/digitalocean branch) and pulled from GHCR;
# never built on the small droplet.

# ── Stage 1: React frontend ──────────────────────────────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /fe
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
# Same origin as the API: nginx serves the app at / and Laravel at /api.
ARG VITE_API_URL=/api/v1
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

# ── Stage 2: runtime (PHP-FPM + nginx) ───────────────────────────────────────
FROM php:8.2-fpm-alpine AS runtime

COPY --from=mlocati/php-extension-installer:2.7.10 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd zip pdo_pgsql opcache \
 && apk add --no-cache nginx \
 && mkdir -p /run/nginx

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
 && rm -f /usr/local/bin/composer \
 && rm -rf frontend node_modules tests .github deploy \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY --from=frontend /fe/dist /var/www/frontend
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-fliperp.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-fliperp.ini
COPY docker/entrypoint.sh /usr/local/bin/fliperp-entrypoint
RUN chmod 755 /usr/local/bin/fliperp-entrypoint

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=5 \
  CMD wget -qO- http://127.0.0.1:8080/up >/dev/null || exit 1

ENTRYPOINT ["fliperp-entrypoint"]
