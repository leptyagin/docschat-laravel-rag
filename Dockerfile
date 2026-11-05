# syntax=docker/dockerfile:1.7

ARG FRANKENPHP_VERSION=1.13
ARG PHP_VERSION=8.5
ARG NODE_VERSION=22

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-trixie AS base

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_HOME=/tmp/composer \
    SERVER_NAME=":80" \
    XDG_CONFIG_HOME=/data/caddy/config \
    XDG_DATA_HOME=/data/caddy/data

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        poppler-utils \
        postgresql-client \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        redis \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid ${GID} app \
    && useradd --uid ${UID} --gid ${GID} --create-home --shell /bin/bash app \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /data /config /app

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app

# ---------------------------------------------------------------------------
# Development: source code is bind-mounted, dependencies installed on the host volume.
# ---------------------------------------------------------------------------
FROM base AS dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/frankenphp/php.dev.ini $PHP_INI_DIR/conf.d/zz-app.ini

USER app

# ---------------------------------------------------------------------------
# Production build stages
# ---------------------------------------------------------------------------
FROM base AS vendor

USER app
COPY --chown=app:app composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

FROM node:${NODE_VERSION}-trixie-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM base AS prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/frankenphp/php.prod.ini $PHP_INI_DIR/conf.d/zz-app.ini

USER app

COPY --chown=app:app --from=vendor /app/vendor ./vendor
COPY --chown=app:app . .
COPY --chown=app:app --from=assets /app/public/build ./public/build

RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
