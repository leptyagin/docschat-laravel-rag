#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" = "frankenphp" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan optimize --no-interaction
fi

exec "$@"
