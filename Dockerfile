FROM php:8.2-apache

# Устанавливаем системные библиотеки для работы с PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev

# Ставим расширения PDO и pgsql
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Включаем mod_rewrite
RUN a2enmod rewrite

# Задаём права и рабочую директорию
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Настраиваем индексные файлы
RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directoryindex.conf

EXPOSE 80
