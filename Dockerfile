# Dockerfile
FROM php:8.4-fpm

# System-Abhängigkeiten
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libxslt1-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        intl \
        xsl \
        opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis
WORKDIR /var/www/html

# Berechtigungen für Symfony-Verzeichnisse setzen
RUN chown -R www-data:www-data /var/www/html
