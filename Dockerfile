# Base image: official PHP-FPM 8.4 (use 8.5 when available)
FROM php:8.4-fpm

# Install system dependencies and PHP extensions (pdo, pgsql/mysql, gd, intl, mbstring, zip, exif)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libonig-dev \
        libzip-dev \
        libicu-dev \
        locales \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd intl mbstring pdo pdo_pgsql pdo_mysql zip exif \
    && docker-php-ext-enable opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy dependency definition files first for better build caching
COPY composer.json composer.lock* ./

# Install PHP dependencies (opt out of dev if desired)
RUN composer install --no-interaction --prefer-dist --no-progress --ansi

# Copy application code
COPY . .

# Optional: install Node.js/npm for Vite builds (uncomment if needed)
# RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
#     && apt-get install -y nodejs \
#     && npm install --production=false

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
