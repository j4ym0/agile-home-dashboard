FROM php:8-apache

ENV TZ=Europe/London

RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    supervisor \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir /database

 RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql

# Create a virtual environment
RUN python3 -m venv /opt/venv

# Put the venv first on PATH
ENV PATH="/opt/venv/bin:$PATH"

COPY service/requirements.txt /tmp/requirements.txt

RUN pip install --no-cache-dir -r /tmp/requirements.txt

RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite

RUN { \
    echo "display_errors=On"; \
    echo "display_startup_errors=On"; \
    echo "error_reporting=E_ALL"; \
    echo "log_errors=On"; \
    echo "error_log=/proc/self/fd/2"; \
} > /usr/local/etc/php/conf.d/errors.ini

COPY web/ /var/www/html/
COPY service/ /service/
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /service \
    && chown -R www-data:www-data /database \
    && mv /var/www/html/htaccess /var/www/html/.htaccess

EXPOSE 80

CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/supervisord.conf"]