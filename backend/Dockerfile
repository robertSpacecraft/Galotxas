FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --no-interaction --optimize

FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        bash \
        gettext \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        nginx \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && apk del .build-deps

WORKDIR /var/www

RUN addgroup -S laravel \
    && adduser -S -D -H -G laravel laravel \
    && addgroup nginx laravel \
    && sed -i 's/user = www-data/user = laravel/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = laravel/' /usr/local/etc/php-fpm.d/www.conf

COPY --from=vendor --chown=laravel:laravel /app /var/www
COPY docker/production/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/production/start.sh /usr/local/bin/start-galotxas

RUN chmod 0755 /usr/local/bin/start-galotxas \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R laravel:laravel storage bootstrap/cache

EXPOSE 8080

CMD ["start-galotxas"]
