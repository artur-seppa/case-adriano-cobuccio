# Carteira financeira

API de uma carteira financeira: cadastro e autenticação, depósito, transferência entre
usuários e reversão de qualquer operação. Saldo pode ficar negativo (é um estado válido);
todo depósito e toda transferência são estornáveis, automaticamente por inconsistência ou a
pedido do usuário.

Laravel 12 como API pura (`/api/v1`), PostgreSQL 16, Redis 7. Frontend e o empacotamento
Docker de produção (FrankenPHP + reverse proxy) ainda não estão neste repositório.

---

## Arquitetura

**Ledger contábil double-entry.** O saldo não é um número solto numa coluna — é a soma de
lançamentos imutáveis (`ledger_entries`). Toda operação de dinheiro é uma `transaction` que
gera **≥ 2 lançamentos** cujo somatório é zero (o que sai de uma carteira entra em outra).
`wallets.balance_cents` é um cache materializado, atualizado sob lock; a verdade é
`SUM(±ledger_entries)`. `wallet:reconcile` prova periodicamente que os dois batem e que a
soma global é zero.

**Conta-sistema `external_world`.** Depósito não tem gateway externo — é crédito interno. Para
o ledger fechar, o depósito **debita** a carteira-sistema `external_world` e credita o
usuário. O saldo (bem negativo) dela é o total já injetado no sistema.

**Dinheiro nunca é float.** `bigint` de centavos no banco, `moneyphp/money` num Value Object
`Money` imutável na aplicação, string decimal (`"150.00"`) no payload da API. Moeda única
`BRL` — o código valida igualdade, nunca converte.

**Camadas leves + Action classes.** Sem repository, sem hexagonal completo. Um `__invoke` por
caso de uso (`DepositFunds`, `TransferFunds`, `ReverseTransaction`). Um único
`LedgerPoster` escreve `transactions` + `ledger_entries` e muta `wallets`. Controllers são
finos: traduzem HTTP ⇄ DTO e delegam à Action.

**Autenticação por sessão** (Fortify headless + Sanctum stateful): cookie `HttpOnly` +
CSRF, mesma origem, sem token no JavaScript, sessão revogável na hora. `personal_access_tokens`
existe mas não é usada no fluxo atual.

**Erros RFC 9457** (`application/problem+json`): contrato estável, sem vazar detalhe interno.
Tratamento central em `bootstrap/app.php`; um `ProblemMapper` cobre cada exceção → status.

**IDs ULID** (`char(26)`) em todo o domínio: k-sortable e não enumeráveis nas rotas.

**Tempo real por SSE** (não WebSocket): quando dinheiro se move, um listener publica um
"nudge" compacto no canal Redis do usuário; `GET /api/v1/stream` encaminha como
`text/event-stream`. O cliente sempre re-busca os números — o payload é só um gatilho.

### Garantias de consistência

| Onde | O quê |
|---|---|
| Aplicação | `DB::transaction(attempts: 3)`, lock pessimista (`lockForUpdate`) nas carteiras em **ordem determinística por `id`** (evita deadlock), assert Σdébito = Σcrédito antes do commit |
| Postgres | `CHECK` de sinal/valor/identidade, índices únicos parciais (1 estorno por transação, `idempotency_key` única), e um **`CONSTRAINT TRIGGER ... DEFERRABLE INITIALLY DEFERRED`** que rejeita no commit qualquer transação desbalanceada |
| Idempotência | **Camada A** — middleware `EnsureIdempotency` + tabela `idempotency_keys`: replay da resposta, `409` em corrida, `422` em key reusada com corpo diferente. **Camada B** — coluna `transactions.idempotency_key` única: backstop estrutural para qualquer caller (console, fila, testes) |
| Reconciliação | `wallet:reconcile` (comando + agendado): compara cache × ledger × último snapshot, e a soma global |

---

## Rodando

```bash
cp .env.example .env
composer install
docker compose up -d        # postgres (bases wallet + wallet_test) + redis
php artisan key:generate
php artisan migrate --seed   # tabelas + carteira external_world (+ dados de demo em local)
```

O compose expõe Postgres em `localhost:5442` e Redis em `localhost:6389` (portas remapeadas
para não colidir com instâncias locais); `.env` e `.env.testing` já apontam para lá.

Em `APP_ENV=local`, o `--seed` também roda o **`DemoSeeder`**: 3 usuários verificados
(`alice@wallet.test`, `bruno@wallet.test`, `carla@wallet.test`, senha `Password1234`), cada um
com carteira financiada, 3 transferências entre eles e 1 estorno — tudo pelas Actions reais, o
`wallet:reconcile` passa em seguida. Roda também isolado com
`php artisan db:seed --class="Database\Seeders\DemoSeeder"`; nunca em produção.

### Testes

```bash
php artisan test             # unit + integração + HTTP + concorrência + arquitetura
./vendor/bin/pint --test     # estilo (preset Laravel)
php artisan wallet:reconcile # invariante contábil — exit 0 saudável, 1 drift
```

Ou via `make`: `up` · `down` · `fresh` · `test` · `pint` · `reconcile` · `docs` · `routes`.

A suíte roda contra **PostgreSQL real** (`wallet_test`), nunca SQLite — o trigger contábil só
dispara em commit de verdade. A suíte `tests/Concurrency` forka processos reais com `pcntl`
(pulada se a extensão não existir) e valida, sob concorrência: ausência de duplo-gasto, corrida
de idempotência, estorno concorrente da mesma transação, e ausência de deadlock em
transferências cruzadas.

---

## API

Base `/api/v1`, JSON only, atrás de `auth:sanctum`. Escritas de dinheiro exigem o header
`Idempotency-Key: <uuid>` (ausência → `400`) e passam por `verified` + rate limit. Toda
resposta carrega `X-Request-Id`.

| Método + rota | |
|---|---|
| `POST /api/register` · `/login` · `/logout` · `/forgot-password` · `/reset-password` | Fortify, sob `/api` (sessão) |
| `GET /api/v1/wallet` | saldo e moeda |
| `GET /api/v1/wallet/statement` | extrato — `ledger_entries`, paginado por cursor via `sequence` |
| `GET /api/v1/wallet/sessions` · `DELETE /api/v1/wallet/sessions/{id}` | sessões ativas / "sair de outro dispositivo" |
| `GET /api/v1/transactions` | filtros `type`, `direction=in\|out`, `from`, `to`, `status`; cursor |
| `GET /api/v1/transactions/{id}` | detalhe + linkagem de estorno |
| `POST /api/v1/deposits` | `{ amount, currency, funding_method?, description? }` → `201` |
| `POST /api/v1/transfers` | `{ recipient (email\|id), amount, currency, description? }` → `201` · `422` saldo/destinatário |
| `POST /api/v1/transactions/{id}/reversal` | usuário estorna a própria operação → `201` · `403` · `409` já estornada |
| `GET /api/v1/stream` | SSE |

**Rate limits:** leituras 60/min · escritas de dinheiro 10/min por usuário · login 5/min por
IP+email · stream 12/min. Estouro → `429` + header `Retry-After`, corpo `problem+json`.

### Documentação da API (OpenAPI 3.1)

| Rota | |
|---|---|
| `GET /docs/api` | UI interativa (Scramble) |
| `GET /docs/api.json` | documento OpenAPI 3.1 cru |
| `php artisan scramble:export --path=openapi.json` | exporta o mesmo documento pra arquivo |

O contrato é derivado **mecanicamente** dos Form Requests e Resources — não há anotação a
manter em dia. Os endpoints são agrupados em seções na UI (Autenticação, Carteira, Transações,
Depósitos, Transferências, Estornos, Sessões, Tempo real). Fora de `local`, `/docs/*` fica
atrás do gate `viewApiDocs`.

---

## Observabilidade

O que já existe no código (dashboards de Pulse/Horizon e `/metrics` Prometheus entram junto
com o empacotamento Docker):

| Recurso | |
|---|---|
| `GET /up` | health check do Laravel (sem auth) |
| `X-Request-Id` | toda resposta carrega um id de correlação (aceita o do cliente ou gera); volta também no corpo de erro (`request_id`) e é injetado no `Context` do log |
| Erros `problem+json` | bugs/invariantes violadas (`UnbalancedLedgerException`, exceção inesperada) → `500` genérico + `Log::critical` com contexto e `request_id`; nunca vazam detalhe interno |
| `php artisan wallet:reconcile` | tripwire da invariante contábil — exit ≠ 0 e `Log::critical` em drift; roda a cada 15 min pelo scheduler e no CI sobre dados semeados |
| `php artisan idempotency:prune` | remove `idempotency_keys` expiradas; agendado de hora em hora |

Agendamento em `routes/console.php`; precisa de um `schedule:work` (ou cron) rodando.

---

## Insights de código

- **`isReversed()` prefere a relação eager-loaded.** "Está estornada?" = existe uma transação
  com `reversal_of_transaction_id` apontando pra ela. Numa listagem isso seria N+1; o método
  usa a relação carregada quando disponível e só cai pra `->exists()` fora de contexto de lista.

- **Política de estorno é só posse.** `TransactionPolicy::reverse` verifica apenas
  `initiator_id === user->id`. "Já estornada" (`409`) e "estorno de estorno" (`422`) são regras
  de negócio revalidadas sob lock pela Action — voltam como erro de domínio, não um `403`
  genérico. Quem **recebeu** uma transferência não pode estorná-la (não é o iniciador).

- **`AssignRequestId` é global** (não por grupo de rota): o spec exige `X-Request-Id` em
  *toda* resposta, inclusive `/up` e páginas de erro — e `/up` não passa por grupo de
  middleware. `SetConnectionTimeouts` (que aperta `lock_timeout`/`statement_timeout` do
  Postgres por request) é o oposto: só nos guards `web`/`api`, nunca em console/fila.

- **`DatabaseTruncation`, não `RefreshDatabase`, nos testes que tocam dinheiro.**
  `RefreshDatabase` embrulha cada teste numa transação que nunca commita — o trigger contábil
  (dispara no commit) e os eventos pós-commit (`DB::afterCommit`) ficariam mudos.

- **Escrita de `idempotency_keys` é transação curta própria**, fora da transação de dinheiro.
  A linha é criada `locked` antes do trabalho; ao terminar vira `completed` com corpo +
  status + `transaction_id`; se a Action lança ou dá `5xx`, a linha `locked` é apagada (o
  cliente pode tentar de novo com a mesma key).

- **Concorrência testada de verdade.** Não com mocks: `tests/Concurrency` roda K processos
  filhos, cada um com conexão própria, contra o Postgres real. É o que dá confiança de que o
  lock ordenado e a idempotência seguram sob corrida.

- **Redis nos testes.** `PublishUserEvent` publica de verdade; a suíte usa o cliente `predis`
  contra o Redis do compose. Sem Redis no ar, os testes de HTTP quebram.

---

## Layout

```
app/Domain/Wallet/        domínio: Enums, ValueObjects/Money, Models, DTOs, Actions,
                          Services/LedgerPoster, Services/BalanceReconciler,
                          Events, Listeners/PublishUserEvent, Exceptions,
                          Policies, Support (ProblemDetails, ProblemMapper, CanonicalJson)
app/Http/
  Controllers/Api/V1/     controllers finos que delegam às Actions
  Middleware/             AssignRequestId, SetConnectionTimeouts, EnsureIdempotency
  Requests/  Resources/   Form Request → DTO; Resources com shape estável
app/Console/Commands/     wallet:reconcile, idempotency:prune
database/migrations/      schema + trigger contábil deferido
routes/api_v1.php         rotas do domínio
routes/console.php        agendamento (reconcile, prune)
tests/                    Unit · Feature · Integration · Concurrency · Arch
```

---

## Comandos

```bash
php artisan migrate --seed          # schema + external_world (+ DemoSeeder em local)
php artisan db:seed --class="Database\Seeders\DemoSeeder"   # dados de demo, standalone
php artisan wallet:reconcile        # verifica a invariante contábil (--fix reescreve o cache a partir do ledger)
php artisan idempotency:prune       # limpa idempotency_keys expiradas
php artisan scramble:export         # exporta o OpenAPI
php artisan route:list --path=api   # todas as rotas da API
php artisan schedule:work           # roda o agendamento (reconcile 15min, prune 1h)
```

## CI

`.github/workflows/backend.yml` sobe Postgres 16 + Redis 7 como service containers e roda:
`pint --test` → `migrate` → `php artisan test` (suíte inteira, incl. concorrência) →
`wallet:reconcile` sobre dados semeados → `scramble:export` (publica `openapi.json` como
artefato). Sem gate de cobertura — coverage é gerável localmente com `php artisan test --coverage`.
