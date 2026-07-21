FROM php:7.1-apache

# PHP 7.1 utiliza Debian Stretch, cuyos repositorios normales
# ya fueron archivados.
RUN sed -ri \
        -e 's/deb.debian.org/archive.debian.org/g' \
        -e 's/security.debian.org/archive.debian.org/g' \
        -e '/stretch-updates/d' \
        /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' \
        > /etc/apt/apt.conf.d/99archive \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Activa las URLs de Laravel e instala el controlador de MySQL.
RUN a2enmod rewrite \
    && docker-php-ext-install pdo_mysql

# Composer 2.2 LTS es compatible con PHP 7.1.
COPY --from=composer:2.2 /usr/bin/composer /usr/local/bin/composer

# Configuración de Apache para Laravel.
COPY docker/apache/000-default.conf \
    /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html