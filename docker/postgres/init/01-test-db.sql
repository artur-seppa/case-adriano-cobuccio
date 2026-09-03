-- Create the database used by `php artisan test` (.env.testing -> DB_DATABASE=wallet_test).
-- Runs once on an empty data dir via the postgres entrypoint. Written idempotently so it
-- is also safe if the pgdata volume is ever reused.
SELECT 'CREATE DATABASE wallet_test OWNER wallet'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wallet_test')\gexec
