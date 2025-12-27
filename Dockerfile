# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        locales \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl mbstring pdo pdo_pgsql pdo_mysql zip exif \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS dev
COPY composer.json composer.lock* ./
RUN composer install --prefer-dist --no-progress --no-scripts
COPY . .

FROM base AS vendor
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-progress --optimize-autoloader --no-scripts

FROM base AS production
ENV APP_ENV=production \
    APP_DEBUG=false \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=10000

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
