FROM php:8.2-apache
RUN apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql mysqli
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
WORKDIR /var/www/html
RUN if [ -f composer.json ]; then composer install --no-dev --no-interaction; fi
EXPOSE 80