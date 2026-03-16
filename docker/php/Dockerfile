FROM php:8.5-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        zip \
        gd \
        pcntl \
        exif

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy only composer files first for layer caching
COPY composer.json composer.lock ./

# Create required directories before composer runs (package:discover needs bootstrap/cache)
RUN mkdir -p bootstrap/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
    && chmod -R 775 bootstrap/cache storage

RUN composer install --no-scripts --no-autoloader --no-interaction

# Copy full application source
COPY . .

# Recreate dirs (in case .dockerignore excluded them), fix ownership, generate autoloader
RUN mkdir -p bootstrap/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && composer dump-autoload --optimize --no-interaction

CMD ["php-fpm"]
