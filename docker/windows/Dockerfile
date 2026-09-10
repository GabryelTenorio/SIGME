FROM php:8.5.9-apache-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl git unzip libicu-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install -j2 pdo_mysql mbstring intl zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:24.19.0-bookworm-slim AS assets
WORKDIR /app
RUN npm install --global npm@12.0.2
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY --from=dependencies /var/www/html /app
RUN npm run build

FROM php-base AS runtime
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
COPY docker/windows/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/windows/php.ini /usr/local/etc/php/conf.d/sigme.ini
COPY --chmod=755 docker/windows/entrypoint.sh /usr/local/bin/sigme-entrypoint
RUN a2enmod rewrite \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && mkdir -p /var/run/apache2 /var/lock/apache2 storage/app/private storage/logs \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 storage bootstrap/cache
USER www-data
EXPOSE 8080
ENTRYPOINT ["sigme-entrypoint"]
CMD ["apache2-foreground"]
