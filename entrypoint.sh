#!/bin/sh
set -e

if [ "$RUN_MIGRATION" = "true" ]; then
  php artisan migrate --force
  php artisan db:seed --force
fi

exec "$@"