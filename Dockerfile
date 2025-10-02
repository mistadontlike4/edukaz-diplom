FROM php:8.2-apache

# Установка расширений PHP для работы с MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Включаем mod_rewrite для Apache
RUN a2enmod rewrite

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html

# Установить index.php как индексный файл
RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directoryindex.conf

EXPOSE 80
