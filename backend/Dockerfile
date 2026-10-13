
FROM php:8.3-apache

# Instalar herramientas y dependencias
RUN apt-get update && apt-get install -y \
    --no-install-recommends \
    libonig-dev \
    curl \
    && docker-php-ext-install pdo_mysql mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Permitir que Apache utilice reglas .htaccess
RUN printf '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' \
    > /etc/apache2/conf-available/incidencias.conf \
    && a2enconf incidencias

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar código del backend
COPY ./src/ /var/www/html/

# Puerto interno de Apache
EXPOSE 80
