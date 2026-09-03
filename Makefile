up:         ; docker compose up -d --build
down:       ; docker compose down
serve:      ; PHP_CLI_SERVER_WORKERS=10 php artisan serve --no-reload
fresh:      ; docker compose exec app php artisan migrate:fresh --seed
test:       ; php artisan test
pint:       ; ./vendor/bin/pint
reconcile:  ; php artisan wallet:reconcile
schedule:   ; php artisan schedule:work
docs:       ; php artisan scramble:export --path=openapi.json
routes:     ; php artisan route:list --path=api
logs:       ; docker compose logs -f
shell:      ; docker compose exec app bash
dtest:      ; docker compose exec app php artisan test
dreconcile: ; docker compose exec app php artisan wallet:reconcile

.PHONY: up down serve fresh test pint reconcile schedule docs routes logs shell dtest dreconcile
