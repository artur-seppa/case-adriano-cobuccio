up:        ; docker compose up -d
down:      ; docker compose down
fresh:     ; php artisan migrate:fresh --seed
test:      ; php artisan test
pint:      ; ./vendor/bin/pint
reconcile: ; php artisan wallet:reconcile

.PHONY: up down fresh test pint reconcile
