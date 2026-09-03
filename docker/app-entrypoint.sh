#!/usr/bin/env bash
set -euo pipefail

php artisan config:cache
php artisan route:cache
php artisan event:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan db:seed --class="Database\\Seeders\\SystemWalletSeeder" --force
fi

exec "$@"
