# syntax=docker/dockerfile:1

# ---- vendor: composer deps ----
# Includes dev deps: this image is the local/demo stack (APP_ENV=local,
# spec's "prod-shaped but runs local"), and Telescope — installed `--dev`
# on purpose, never auto-discovered, gated behind `environment('local')` —
# needs to actually be present for the CP5 `/telescope` verification. A real
# production build (out of scope here) would use its own `--no-dev` image.
FROM composer:2 AS vendor
RUN install-php-extensions bcmath pcntl
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize

# ---- runtime: FrankenPHP + Octane ----
FROM dunglas/frankenphp:1-php8.3 AS runtime
RUN install-php-extensions redis pcntl sockets pdo_pgsql opcache bcmath intl \
    && apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app

# non-root
RUN useradd -u 10001 -m -s /bin/bash app
COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache

COPY --chown=app:app docker/app-entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

USER app
EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=3s --start-period=40s --retries=6 \
  CMD curl -fsS http://localhost:8000/health || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=500"]
