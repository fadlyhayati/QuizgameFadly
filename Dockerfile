FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo_mysql \
    && a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*; a2enmod mpm_prefork >/dev/null 2>&1 || true; PORT=${PORT:-8080}; printf 'Listen %s\n' \"${PORT}\" > /etc/apache2/ports.conf && sed -ri \"s/:[0-9]+>/:${PORT}>/g\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
