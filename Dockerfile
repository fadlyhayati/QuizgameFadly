FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["sh", "-c", "PORT=${PORT:-80}; sed -ri \"s/Listen [0-9]+/Listen ${PORT}/g\" /etc/apache2/ports.conf && sed -ri \"s/:[0-9]+>/:${PORT}>/g\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
