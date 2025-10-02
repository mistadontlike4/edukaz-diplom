FROM php:8.2-apache

# Устанавливаем расширения для работы с MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Включаем mod_rewrite (часто нужен для PHP проектов)
RUN a2enmod rewrite

# Задаем рабочую директорию
WORKDIR /var/www/html

# Копируем все файлы проекта внутрь контейнера
COPY . /var/www/html

# Настраиваем права
RUN chown -R www-data:www-data /var/www/html

# Настраиваем index.php как стартовый файл
RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directoryindex.conf

EXPOSE 80
