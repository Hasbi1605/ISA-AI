FROM php:8.4-cli-bookworm AS vendor

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    git \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pcntl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist


FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

RUN npm run build


FROM php:8.4-cli-bookworm AS app

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    curl \
    git \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath gd intl mbstring pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/99-ista.ini
COPY docker/entrypoint.sh /usr/local/bin/ista-entrypoint
COPY docker/serve.sh /usr/local/bin/ista-serve

RUN chmod +x /usr/local/bin/ista-entrypoint \
    && chmod +x /usr/local/bin/ista-serve \
    && rm -f database/database.sqlite \
    && mkdir -p \
        bootstrap/cache \
        /usr/local/share/ista-bootstrap-cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && php artisan package:discover --ansi \
    && cp bootstrap/cache/packages.php /usr/local/share/ista-bootstrap-cache/packages.php \
    && cp bootstrap/cache/services.php /usr/local/share/ista-bootstrap-cache/services.php \
    && chown -R www-data:www-data bootstrap/cache storage

EXPOSE 8000

ENTRYPOINT ["ista-entrypoint"]
CMD ["ista-serve"]
