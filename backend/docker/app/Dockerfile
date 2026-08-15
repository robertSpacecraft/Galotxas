FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
    pdo_mysql \
    bcmath \
    exif \
    gd \
    intl \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-media.ini

WORKDIR /var/www

# 👇 añadir esto
RUN addgroup -g 1000 laravel \
    && adduser -D -u 1000 -G laravel laravel

RUN sed -i 's/user = www-data/user = laravel/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = laravel/g' /usr/local/etc/php-fpm.d/www.conf
