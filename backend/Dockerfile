# syntax=docker/dockerfile:1

# ---- Stage 1: Composer dependencies (no dev) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Install without running scripts (no app boot / DB needed at build time).
# ext-soap/ext-sodium are provided by the runtime image, not the composer image.
RUN composer install \
    --no-dev \
    --no-scripts \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --ignore-platform-req=ext-soap \
    --ignore-platform-req=ext-sodium
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- Stage 2: PHP-FPM runtime ----
FROM php:8.3-fpm-alpine AS runtime

# PHP extensions: PostgreSQL + Redis + OPcache + SOAP/sodium (GAM API) + bcmath.
RUN set -eux; \
    apk add --no-cache postgresql-libs libsodium libxml2; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libsodium-dev libxml2-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql opcache bcmath soap sodium; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps

# Production OPcache + php.ini tweaks.
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# App code with vendored dependencies.
COPY --from=vendor /app /var/www/html

# Writable runtime dirs.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
