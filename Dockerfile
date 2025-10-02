FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html

# Вставьте сюда:
RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directoryindex.conf

EXPOSE 80