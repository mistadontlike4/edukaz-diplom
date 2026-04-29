FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev
RUN docker-php-ext-install pdo pdo_pgsql pgsql

RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directoryindex.conf

# ✅ добавляем стартовый скрипт
COPY start-apache.sh /usr/local/bin/start-apache.sh
RUN chmod +x /usr/local/bin/start-apache.sh

# ✅ запускаем через скрипт (он гарантирует один MPM)
CMD ["/usr/local/bin/start-apache.sh"]

EXPOSE 80