# Carteira financeira — Backend (Planos 1–2: domínio, ledger e API)

Desafio Full Stack PHP — Grupo Adriano Cobuccio. **Plano 1** entregou a camada de domínio e o
ledger contábil double-entry; **Plano 2** expõe tudo como API REST `/api/v1` autenticada por
sessão (Fortify + Sanctum), com idempotência, erros RFC 9457, rate limiting e SSE. Frontend
(Plano 3) e infra FrankenPHP/Traefik (Plano 4) ainda não. Design completo em
`docs/superpowers/specs/2026-08-31-carteira-financeira-design.md`.

## Requisitos

- PHP 8.3+ com extensões `intl`, `pdo_pgsql`, `pcntl` (concorrência); `phpredis` opcional
  (os testes usam o cliente `predis`)
- Docker + Docker Compose (Postgres + Redis)
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

## API (Plano 2)

- **Base:** `/api/v1` — JSON only, auth por sessão via cookie `HttpOnly` do Sanctum
  (SPA stateful, mesma origem). CSRF via `GET /sanctum/csrf-cookie` + header `X-XSRF-TOKEN`.
- **Auth (Fortify, sob `/api`):** `POST /register`, `POST /login`, `POST /logout`,
  `POST /forgot-password`, `POST /reset-password`, `GET /email/verify/{id}/{hash}`,
  `POST /email/verification-notification`. `POST /register` cria o usuário **e** a carteira
  numa transação; responde `201 { user, requires_email_verification }`.
- **Domínio (`auth:sanctum`):** `GET /wallet`, `GET /wallet/statement` (cursor por `sequence`),
  `GET /wallet/sessions`, `DELETE /wallet/sessions/{id}`, `GET /transactions`
  (filtros `type`, `direction=in|out`, `from`, `to`, `status`, cursor), `GET /transactions/{id}`,
  `POST /deposits`, `POST /transfers`, `POST /transactions/{id}/reversal`, `GET /stream` (SSE).
- **Idempotência:** `POST /deposits`, `/transfers`, `/transactions/{id}/reversal` exigem o header
  `Idempotency-Key: <uuid>` (ausência → `400`). Repetir a mesma key + corpo replica a resposta
  gravada (`Idempotency-Replayed: true`); key reusada com corpo diferente → `422`; em andamento → `409`.
- **Erros:** `application/problem+json` (RFC 9457) — `type` (URI estável), `title`, `status`,
  `detail?`, `request_id` + membros extras (`errors`, `available`/`requested`, ...).
- **`X-Request-Id`** em toda resposta (aceita o do cliente ou gera um).
- **Rate limits:** leituras 60/min · escritas de dinheiro 10/min · login 5/min (por IP+email) · stream 12/min.
- **Tempo real:** `GET /api/v1/stream` (SSE) encaminha um "nudge" compacto por `Redis::publish`
  quando dinheiro se move; o cliente sempre re-busca os números autoritativos.
- **OpenAPI 3.1:** `GET /docs/api` (UI) · `GET /docs/api.json` · `php artisan scramble:export`.

## Estrutura

Domínio em `app/Domain/Wallet/` (enums, Value Object `Money`, models, DTOs, exceptions,
`LedgerPoster`, Actions, eventos, `BalanceReconciler`, `PublishUserEvent`). Camada HTTP em
`app/Http/` (controllers finos `Api/V1/*` que delegam às Actions, Form Requests → DTO,
Resources, middleware `AssignRequestId`/`SetConnectionTimeouts`/`EnsureIdempotency`). Ver o
spec para o racional de cada decisão.

```bash
make up          # sobe postgres + redis
make fresh       # migrate:fresh --seed
make test        # php artisan test
make pint        # ./vendor/bin/pint
make reconcile   # php artisan wallet:reconcile
make docs        # exporta openapi.json
make routes      # php artisan route:list --path=api
```

> Testes usam `predis` + o Redis do compose (`127.0.0.1:6389`); produção usa a extensão
> `phpredis` (Plano 4). Sem Redis a suíte de HTTP falha (o `PublishUserEvent` publica de verdade).
