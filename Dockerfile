FROM composer:2.2.29 AS composer

FROM php:7.1.0-apache

# PHP 7.1.0 usa Debian Jessie, cuyos repositorios están archivados.
RUN sed -ri \
        -e 's!deb.debian.org/debian!archive.debian.org/debian!g' \
        -e 's!httpredir.debian.org/debian!archive.debian.org/debian!g' \
        -e 's!security.debian.org/debian-security!archive.debian.org/debian-security!g' \
        -e 's!security.debian.org!archive.debian.org/debian-security!g' \
        -e '/jessie-updates/d' \
        -e '/stretch-updates/d' \
        /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\nAcquire::AllowInsecureRepositories "true";\n' \
        > /etc/apt/apt.conf.d/99archive \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        libpq-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        dom \
        mbstring \
        pdo_pgsql \
        xml \
        xmlwriter \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
