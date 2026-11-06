#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" = "frankenphp" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    if [ "${DB_PASSWORD:-}" = "secret" ]; then
        echo "Refusing to start: DB_PASSWORD still has the example value." >&2
        exit 1
    fi

    if [ -n "${ADMIN_PASSWORD:-}" ] && [ "${#ADMIN_PASSWORD}" -lt 16 ]; then
        echo "Refusing to start: ADMIN_PASSWORD is shorter than 16 characters." >&2
        exit 1
    fi

    if [ "${REDIS_PASSWORD:-}" = "null" ]; then
        echo "Refusing to start: REDIS_PASSWORD still has the example value." >&2
        exit 1
    fi

    php artisan optimize --no-interaction
fi

exec "$@"
