FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libpq-dev \
    oniguruma-dev \
    unzip \
    zip \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pcntl \
    pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

COPY . .

RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public public/index.php"]
