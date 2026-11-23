FROM php:8.3-apache

# pdo_pgsql is the only extension missing from the official image (curl and json are built in).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf && a2enconf servername

WORKDIR /var/www/html

# Dependencies first, so code changes do not invalidate this layer.
# Dev dependencies (PHPUnit) are installed on purpose: this image is the local test environment.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize
