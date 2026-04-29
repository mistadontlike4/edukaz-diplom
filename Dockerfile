FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Включаем mod_rewrite
RUN a2enmod rewrite

# ✅ FIX: оставляем только один MPM (prefork) для mod_php
RUN a2dismod mpm_event mpm_worker || true \
 && a2enmod mpm_prefork \
 && apache2ctl -t

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directoryindex.conf

EXPOSE 80