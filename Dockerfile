FROM php:8.2-apache

RUN apt-get update         && apt-get install -y --no-install-recommends             libfreetype6-dev             libicu-dev             libjpeg62-turbo-dev             libpng-dev             libwebp-dev             libzip-dev             zlib1g-dev         && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp         && docker-php-ext-install -j$(nproc) gd intl zip         && a2enmod rewrite headers access_compat         && sed -ri 's!www-data!daemon!g' /etc/apache2/envvars         && rm -rf /var/lib/apt/lists/*

COPY apache-tecnobot.conf /etc/apache2/conf-enabled/apache-tecnobot.conf

WORKDIR /var/www/html

EXPOSE 80
