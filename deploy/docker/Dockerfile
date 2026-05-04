FROM php:8.3-fpm-alpine

LABEL maintainer="adniba-backend"

# ── System deps ───────────────────────────────────────────────────────────────
RUN apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        opcache \
        pcntl \
    # Redis PHP extension via PECL
    && pecl install redis \
    && docker-php-ext-enable redis

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Working directory ─────────────────────────────────────────────────────────
WORKDIR /var/www/adniba

# ── Install PHP dependencies (layer-cached) ───────────────────────────────────
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# ── Copy application source ───────────────────────────────────────────────────
COPY . .

# ── Post-install artisan scripts ──────────────────────────────────────────────
RUN composer run-script post-autoload-dump && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# ── File permissions ──────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/adniba \
    && chmod -R 755 /var/www/adniba/storage \
    && chmod -R 755 /var/www/adniba/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
