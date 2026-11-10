FROM php:8.2-apache
RUN apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork rewrite
RUN apache2ctl -M | grep -i mpm
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
WORKDIR /var/www/html
RUN if [ -f composer.json ]; then composer install --no-dev --no-interaction; fi
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads
EXPOSE 80