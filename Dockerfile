FROM php:8.2-apache

# Установка расширений PHP для работы с MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Включаем mod_rewrite для Apache
RUN a2enmod rewrite

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
