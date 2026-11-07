# --- Dépendances PHP (sans dev) -------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# --- Image finale ----------------------------------------------------------
FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-libs libzip sqlite-libs oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev sqlite-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath mbstring pdo_sqlite zip \
    && apk del .build-deps \
    && rm -rf /tmp/*

WORKDIR /var/www

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
       storage/logs storage/app/backups storage/app/exports bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
