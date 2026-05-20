# Stage 1: Builder
FROM php:8.3-fpm-alpine AS builder

# Install system dependencies & PHP extensions required for Laravel & PostgreSQL
RUN apk add --no-cache \
    libzip-dev \
    postgresql-dev \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files (if they exist, else we'll map volume in dev)
# We copy everything in builder to prep the artifact
COPY . .

# Install dependencies and generate optimized autoload files without running post-autoload-dump scripts
# Disabling TLS temporarily to bypass corporate firewall/proxy SSL certificate interception
RUN if [ -f "composer.json" ]; then \
    composer config -g disable-tls true && \
    composer config -g secure-http false && \
    composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist; \
    fi

# Stage 2: Runner (Production ready Alpine image)
FROM php:8.3-fpm-alpine AS runner

# Install build dependencies, compile extensions, then remove build dependencies and keep runtime dependencies
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libzip-dev \
    postgresql-dev \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && apk add --no-cache \
    libzip \
    postgresql-libs \
    libpng \
    jpeg \
    freetype \
    oniguruma

WORKDIR /var/www

# Copy application from builder
COPY --from=builder /var/www /var/www

# Set appropriate permissions for Laravel (creating directories if they don't exist yet)
RUN mkdir -p /var/www/storage/app/public \
    /var/www/storage/framework/cache/data \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/testing \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Switch to non-root user
USER www-data

EXPOSE 9000
CMD ["php-fpm"]
