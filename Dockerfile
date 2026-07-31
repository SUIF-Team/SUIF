FROM composer:2.10.2 AS composer
FROM node:24.18-bookworm AS node

FROM php:8.4.23-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libpq-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /opt /opt
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
