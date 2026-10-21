FROM php:8.4-fpm-alpine

LABEL maintainer="Archivage" description="PHP 8.4-fpm pour Archivage (Laravel 13)"

RUN apk add --no-cache \
        bash git curl unzip libpng-dev oniguruma-dev libxml2-dev \
        postgresql-dev icu-dev zip libzip-dev linux-headers autoconf \
        $PHPIZE_DEPS

# Extensions PHP — toutes celles dont Laravel 13 et notre app ont besoin
RUN docker-php-ext-install \
        pdo pdo_pgsql mbstring exif pcntl bcmath gd intl zip opcache

# Outils d'intégrité et manipulation d'archives côté app
RUN apk add --no-cache \
        imagemagick-dev imagemagick-libs \
        ghostscript \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis

# Composer 2.x
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 (pour compiler d'éventuels assets Laravel si besoin)
RUN apk add --no-cache nodejs npm

WORKDIR /var/www/backend

# Configuration PHP pour prod (mémoire, opcache)
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=220M'; \
        echo 'post_max_size=220M'; \
        echo 'max_execution_time=120'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
    } > /usr/local/etc/php/conf.d/zz-archivage.ini

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
