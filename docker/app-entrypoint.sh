#!/usr/bin/env bash
set -euo pipefail

php artisan config:cache
php artisan route:cache
php artisan event:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan db:seed --class="Database\\Seeders\\SystemWalletSeeder" --force
  # One integrity pass at boot so `docker compose logs app` shows the ledger
  # invariant held on the freshly migrated/seeded data, instead of waiting up
  # to 15m for the scheduler's first run. Non-fatal (`set -e` is on): a drift
  # finding logs CRITICAL + moves the Prometheus gauge, it must not block boot.
  php artisan wallet:reconcile || true
fi

exec "$@"
