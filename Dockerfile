FROM php:8-apache

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN a2enmod rewrite

RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql

RUN mkdir /database && \
    chown www-data:www-data /database && \
    chmod 775 /database

COPY --chown=www-data:www-data ./web /var/www/html
