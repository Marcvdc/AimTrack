# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# postgresql-client-16 uit de PGDG-repository: bookworm heeft alleen client 15, en
# pg_dump weigert een nieuwere server (de database is postgres:16). Het sleutelbestand
# gaat via een tijdelijke keyring, en alleen de sleutel met deze vingerafdruk komt in
# het signed-by-bestand: een extra meegeleverde sleutel wordt zo niet vertrouwd. gnupg
# is alleen daarvoor nodig en gaat in dezelfde laag weer weg.
# Bijeffect: met deze bron komen ook libpq5 en libpq-dev uit PGDG, dus pdo_pgsql
# linkt tegen de libpq van PGDG en niet tegen die van bookworm.
ARG PGDG_KEY_FINGERPRINT=B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && install -d -m 755 /etc/apt/keyrings \
    && export GNUPGHOME="$(mktemp -d)" \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --batch --import \
    && gpg --batch --export "${PGDG_KEY_FINGERPRINT}" > /etc/apt/keyrings/pgdg.gpg \
    && test -s /etc/apt/keyrings/pgdg.gpg \
    && rm -rf "${GNUPGHOME}" && unset GNUPGHOME \
    && apt-get purge -y --auto-remove gnupg \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
        git \
        rsync \
        unzip \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        locales \
        libonig-dev \
        postgresql-client-16 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl mbstring pdo pdo_pgsql pdo_mysql zip exif \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS vendor
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-progress --optimize-autoloader --no-scripts

FROM node:20-bookworm AS frontend
WORKDIR /app
COPY . .
# De Filament theme (resources/css/filament/admin/theme.css) importeert CSS uit
# vendor/filament/...; bij een verse CI-checkout bestaat vendor/ nog niet, dus
# kopieer 'm uit de vendor-stage vóór de vite/Tailwind-build. De vendor-stage
# staat daarom bewust vóór deze stage (BuildKit vereist eerdere definitie).
COPY --from=vendor /var/www/html/vendor ./vendor
RUN mkdir -p /opt/artifacts/public-build \
    && if [ -f package.json ]; then \
         npm ci --no-progress --fund=false --audit=false; \
         npm run build --if-present; \
       else \
         echo "[frontend] package.json not found, skipping asset build"; \
       fi \
    && if [ -d public/build ]; then \
         cp -r public/build/. /opt/artifacts/public-build/; \
       fi

FROM base AS dev
COPY composer.json composer.lock* ./
RUN composer install --prefer-dist --no-progress --no-scripts
COPY . .

FROM base AS production
ENV APP_ENV=production \
    APP_DEBUG=false \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=10000

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
RUN chmod +x docker/backup/*.sh scripts/backup-dev-db.sh
COPY --from=frontend /opt/artifacts/public-build ./public/build

# Pristine kopie van de volledige applicatie. De entrypoint synct deze bij elke
# boot naar /var/www/html (het gedeelde app_code-volume), zodat een named volume
# na de eerste deploy niet de oude code blijft serveren. Zie docker/entrypoint.sh.
RUN cp -a /var/www/html /opt/aimtrack

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
