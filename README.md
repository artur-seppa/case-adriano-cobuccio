# Carteira financeira — Backend (Plano 1: domínio + ledger)

Desafio Full Stack PHP — Grupo Adriano Cobuccio. Este é o **Plano 1 de 4**: fundação Laravel,
camada de domínio e ledger contábil double-entry, sem HTTP/auth/frontend (Planos 2–4).
Design completo em `docs/superpowers/specs/2026-08-31-carteira-financeira-design.md`.

## Requisitos

- PHP 8.3+ com extensões `intl`, `pdo_pgsql`, `pcntl` (a última só é usada pela suíte de concorrência)
- Docker + Docker Compose
- Composer

## Setup

```bash
cp .env.example .env
composer install
docker compose up -d          # postgres (wallet + wallet_test) + redis
php artisan key:generate
php artisan migrate --seed
```

`docker compose` expõe Postgres em `localhost:5442` e Redis em `localhost:6389` (portas
remapeadas para evitar conflito com instâncias locais) — já refletido em `.env` / `.env.testing`.

## Testes

```bash
php artisan test              # unit + integração + concorrência + arquitetura
./vendor/bin/pint --test      # estilo
php artisan wallet:reconcile  # invariante contábil (exit 0 = saudável, 1 = drift)
```

A suíte roda contra Postgres real (`wallet_test`), nunca SQLite — o gatilho de consistência
contábil (`CONSTRAINT TRIGGER ... DEFERRABLE INITIALLY DEFERRED`) só dispara em commit real, o
que exige `DatabaseTruncation` (não `RefreshDatabase`) nos grupos `Integration` e `Concurrency`.

A suíte `tests/Concurrency` forka processos reais via `pcntl` para validar, sob concorrência de
verdade: ausência de duplo-gasto, corrida de idempotência, reversão concorrente da mesma
transação e ausência de deadlock em transferências cruzadas. Sem `pcntl`, esses testes são
pulados automaticamente.

## Estrutura

Domínio em `app/Domain/Wallet/` (enums, Value Object `Money`, models, DTOs, exceptions,
`LedgerPoster`, Actions, eventos, `BalanceReconciler`). Ver o spec para o racional de cada
decisão (double-entry, carteira `external_world`, lock pessimista + ordenação determinística,
idempotência em duas camadas, política de reversão).

```bash
make up          # sobe postgres + redis
make fresh       # migrate:fresh --seed
make test        # php artisan test
make pint        # ./vendor/bin/pint
make reconcile   # php artisan wallet:reconcile
```
