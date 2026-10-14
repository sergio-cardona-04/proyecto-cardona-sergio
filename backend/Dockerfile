FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    --no-install-recommends \
    libonig-dev \
    curl \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN printf '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' \
    > /etc/apache2/conf-available/incidencias.conf \
    && a2enconf incidencias

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /opt/incidencias

COPY composer.json composer.lock ./

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

WORKDIR /var/www/html

COPY ./src/ /var/www/html/

EXPOSE 80
