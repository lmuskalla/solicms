FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build


FROM php:8.4-cli-bookworm AS app
ARG UID=33
ARG GID=33

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        redis \
        zip \
        @composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        gifsicle \
        jpegoptim \
        optipng \
        pngquant \
        sqlite3 \
        unzip \
        webp \
    && rm -rf /var/lib/apt/lists/*

# www-data already exists in this base image; remap it only if the host
# needs a specific uid/gid to match bind-mounted directory ownership.
RUN groupmod --gid ${GID} www-data \
    && usermod --uid ${UID} --gid ${GID} www-data

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
USER www-data
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

USER root
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN ln -sf ../storage/app/public public/storage

USER www-data
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
