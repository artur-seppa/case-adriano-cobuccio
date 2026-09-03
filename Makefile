up:        ; docker compose up -d
down:      ; docker compose down
serve:     ; PHP_CLI_SERVER_WORKERS=10 php artisan serve --no-reload
fresh:     ; php artisan migrate:fresh --seed
test:      ; php artisan test
pint:      ; ./vendor/bin/pint
reconcile: ; php artisan wallet:reconcile
schedule:  ; php artisan schedule:work
docs:      ; php artisan scramble:export --path=openapi.json
routes:    ; php artisan route:list --path=api

.PHONY: up down serve fresh test pint reconcile schedule docs routes
