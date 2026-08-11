FROM php:8.4-cli-bookworm AS base

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        supervisor \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        pcntl \
        sockets \
        opcache \
        exif \
        gd \
    && rm -rf /var/lib/apt/lists/*

# igbinary and phpredis are built from GitHub source rather than `pecl install` —
# this environment cannot resolve/reach pecl.php.net, but github.com is reachable.
RUN git clone --depth 1 https://github.com/igbinary/igbinary.git /tmp/igbinary \
    && cd /tmp/igbinary \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable igbinary \
    && cd / && rm -rf /tmp/igbinary

RUN git clone --depth 1 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure --enable-redis-igbinary \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable redis \
    && cd / && rm -rf /tmp/phpredis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-umrany.ini

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]
