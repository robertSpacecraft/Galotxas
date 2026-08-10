#!/usr/bin/env bash

set -euo pipefail

PORT="${PORT:-8080}"

if [[ ! "${PORT}" =~ ^[0-9]+$ ]] || (( PORT < 1 || PORT > 65535 )); then
  printf 'PORT must be an integer between 1 and 65535.\n' >&2
  exit 64
fi

export PORT
envsubst '${PORT}' \
  < /etc/nginx/http.d/default.conf.template \
  > /etc/nginx/http.d/default.conf

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

php-fpm --daemonize
exec nginx -g 'daemon off;'
