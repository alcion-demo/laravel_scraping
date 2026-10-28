
FROM php:8.5.11-fpm-alpine3.24

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    vim \
    curl \
    zip \
    unzip \
    icu-dev \
    git \
    mariadb-connector-c-dev \
    mariadb-client \
    libzip-dev \
    libpng-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    zlib-dev

RUN docker-php-ext-configure intl

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

RUN docker-php-ext-install -j"$(nproc)" \
    intl \
    pdo_mysql \
    zip \
    gd
