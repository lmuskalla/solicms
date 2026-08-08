FROM php:8.4-cli-bookworm

# Match the host user so bind-mounted files aren't written as root.
ARG UID=1000
ARG GID=1000

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

# sqlite3: tenant/central databases.
# The image optimisers are what spatie/laravel-medialibrary shells out to when
# generating conversions; without them it silently skips optimisation.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        gifsicle \
        jpegoptim \
        optipng \
        pngquant \
        sqlite3 \
        unzip \
        webp \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd --gid ${GID} app \
    && useradd --uid ${UID} --gid ${GID} --create-home --shell /bin/bash app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html
USER app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
