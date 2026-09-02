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
docker compose up -d        # postgres (wallet + wallet_test) + redis + mailpit
php artisan key:generate
php artisan migrate --seed   # tabelas + carteira external_world (+ dados de demo em local)

php artisan serve            # sobe a API em http://localhost:8000
```

Com a API no ar: contrato em `http://localhost:8000/docs/api`, e um cron/worker de
agendamento com `php artisan schedule:work` (reconcile a cada 15 min, prune de hora em hora).
O empacotamento de produção (FrankenPHP/Octane + reverse proxy) ainda não está no repositório.

O compose expõe Postgres em `localhost:5442`, Redis em `localhost:6389` (portas remapeadas
para não colidir com instâncias locais) e o **Mailpit** em `localhost:8025` — os e-mails de
verificação de conta e de reset de senha caem lá. `.env` e `.env.testing` já apontam para tudo.

O link de reset de senha aponta para o SPA (`FRONTEND_URL`, default `http://localhost:3000`) —
`/reset-password?token=...&email=...`; sem frontend rodando, pegue o `token` da URL no Mailpit
e chame `POST /api/reset-password` direto.

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

Ou via `make`: `up` · `down` · `serve` · `fresh` · `test` · `pint` · `reconcile` · `schedule` · `docs` · `routes`.

A suíte roda contra **PostgreSQL real** (`wallet_test`), nunca SQLite — o trigger contábil só
dispara em commit de verdade. A suíte `tests/Concurrency` forka processos reais com `pcntl`
(pulada se a extensão não existir) e valida, sob concorrência: ausência de duplo-gasto, corrida
de idempotência, estorno concorrente da mesma transação, e ausência de deadlock em
transferências cruzadas.

---

## API

Base `/api/v1`, JSON only, atrás de `auth:sanctum`. Escritas de dinheiro exigem o header
`Idempotency-Key: <uuid>` (ausência → `400`) e passam por `verified` + rate limit. Toda
resposta carrega `X-Request-Id`. `GET /` devolve um índice JSON com os links de docs/health.

Auth é por **sessão** (cookie `HttpOnly` do Sanctum, mesma origem) — não é bearer token. Toda
rota de escrita (auth **e** dinheiro) é CSRF-protected. Para testar via `curl`/Postman:

```bash
curl -c cookies.txt http://localhost:8000/sanctum/csrf-cookie        # grava o cookie XSRF-TOKEN
# em cada POST: -b cookies.txt  e  -H "X-XSRF-TOKEN: <valor do cookie, url-decoded>"
```

Sem isso → `419 CSRF token mismatch`. No `/docs/api` (Stoplight) o "Try it" já manda o cookie.

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

### Modelagem de dados

- **Dinheiro é `bigint` de centavos, nunca float.** Coluna `bigint` **signed** no Postgres (a
  carteira precisa de saldo negativo), Value Object `Money` imutável (`moneyphp/money`) na
  aplicação, string decimal (`"150.00"`) no payload. Aritmética sempre inteira; moeda única
  `BRL` — o código valida igualdade, nunca converte. Até a regra `AsMoney` checa `> 0` por
  regex, sem `(float)`.

- **PKs ULID (`char(26)`).** K-sortable como um auto-increment (bom pra localidade de índice),
  mas não enumerável nas rotas — não dá pra chutar `/transactions/5`. Gera o id antes do
  insert, sem round-trip.

- **`transactions` e `ledger_entries` são append-only.** A aplicação **nunca** faz
  `UPDATE`/`DELETE` nelas. Estorno é uma transação **nova** do tipo `reversal`, com lançamentos
  espelhados, ligada à original por `reversal_of_transaction_id`. Só `wallets.balance_cents` e
  `entry_count` são mutáveis.

- **`sequence` — contador monotônico por carteira** nos lançamentos (`UNIQUE (wallet_id,
  sequence)`, sem buraco). Dá ordem determinística mesmo com timestamps empatados, torna o
  `balance_after` inequívoco e é a âncora da paginação por cursor do extrato.

- **`balance_after_cents` gravado em cada lançamento.** O extrato mostra saldo corrente sem
  recomputar, e a reconciliação vira O(1) (compara o último snapshot com o cache).

- **Conta-sistema `external_world`.** Depósito não tem gateway externo: debita essa carteira,
  credita o usuário. O saldo (bem negativo) dela = total já injetado no sistema. É o que faz o
  double-entry fechar sem uma contraparte real.

### Concorrência e consistência

- **Lock pessimista com ordem determinística.** `SELECT … FOR UPDATE` nas linhas das carteiras
  envolvidas, **sempre** ordenadas por `id` ascendente. Transfer A→B e B→A concorrentes pegam
  os locks na mesma ordem → sem deadlock. Otimista (versão + retry) foi descartado: carteira
  popular geraria retry demais.

- **`DB::transaction(attempts: 3)`.** Deadlock (`40P01`) e serialization failure (`40001`)
  reexecutam a closure com backoff + jitter. Esgotou → `503` + `Retry-After`. Efeito externo
  (Redis, e-mail) **fora** da closure, sempre pós-commit (`DB::afterCommit`).

- **Trigger contábil deferido.** `CONSTRAINT TRIGGER … DEFERRABLE INITIALLY DEFERRED` roda a
  checagem Σdébito = Σcrédito **no commit** — os lançamentos podem entrar em qualquer ordem
  dentro da transação. A aplicação também faz o assert antes (mensagem melhor, falha mais
  cedo); o trigger é o backstop que torna um ledger desbalanceado impossível de persistir.

- **Isolamento `READ COMMITTED`** (default do Postgres). Suficiente porque o lock pessimista é
  explícito — sem `REPEATABLE READ`/`SERIALIZABLE`.

- **Timeouts por request.** `SET lock_timeout='3s'` / `statement_timeout='5s'` só nos guards
  `web`/`api` (nunca console/fila — migration e reconcile precisam de mais). Reaplicado a cada
  request → seguro sob Octane.

- **Idempotência em duas camadas.** Middleware `EnsureIdempotency` + tabela `idempotency_keys`
  (grava a resposta e faz replay; `409` em corrida, `422` em key reusada com corpo diferente,
  `400` sem header) sobre um `UNIQUE` em `transactions.idempotency_key` (backstop estrutural
  pra qualquer caller — console, fila, teste). A escrita em `idempotency_keys` é transação
  curta própria: linha `locked` antes do trabalho, `completed` com corpo+status+`transaction_id`
  ao fim, apagada se a Action lançar ou der `5xx` (o cliente reusa a mesma key).

### Reconciliação

- **`wallet:reconcile` prova três representações do saldo.** `balance_cents` (cache) ==
  `SUM(±ledger_entries)` (a verdade) == `balance_after_cents` do lançamento de maior `sequence`
  (snapshot), e `SUM(todas wallets.balance_cents) == 0` (soma global zero do double-entry).
  Roda a cada 15 min pelo scheduler e no CI sobre dados semeados; exit ≠ 0 e `Log::critical` em
  drift. `--fix` reescreve o cache a partir do ledger (manual — humano decide).

### Fila e agendamento

- **`PublishUserEvent` é síncrono, pós-commit.** É só um `Redis::publish` barato pro SSE — não
  vale uma fila. Envolvido em `try/catch`: Redis fora do ar não transforma um depósito que
  **commitou** num `500`. Os listeners de e-mail/audit/métrica é que iriam pra fila (Redis +
  Horizon, no empacotamento Docker).

- **Scheduler em `routes/console.php`.** `wallet:reconcile` (15 min) e `idempotency:prune`
  (horário). Precisa de um `schedule:work` (ou cron) rodando.

### API e HTTP

- **Paginação por cursor.** O extrato (`/wallet/statement`) pagina por `sequence` desc — não
  offset. `WHERE sequence < :ultimo ORDER BY sequence DESC LIMIT n` bate direto no índice,
  O(1) por página, e é estável sob inserção concorrente (lançamento novo entra no topo, nunca
  desloca as páginas). `/transactions` usa o mesmo esquema com `(created_at, id)`. Sem `total`
  — o trade-off do cursor.

- **Erros RFC 9457 (`application/problem+json`).** Um `ProblemMapper` central mapeia cada
  exceção → status, com `type` URI estável. Bug/invariante violada → `500` genérico +
  `Log::critical` com contexto e `request_id`; nunca vaza detalhe interno. Idempotência
  (400/409/422) responde direto no middleware.

- **`X-Request-Id` em toda resposta** (inclusive erro). `AssignRequestId` é **global** — o spec
  exige o header até no `/up`, que não passa por grupo de middleware. O id volta no corpo do
  erro (`request_id`) e entra no `Context` do log.

- **`isReversed()` prefere a relação eager-loaded.** "Está estornada?" = existe transação com
  `reversal_of_transaction_id` apontando pra ela. Em listagem seria N+1; o método usa a relação
  carregada quando disponível, só cai pra `->exists()` fora de contexto de lista.

- **Política de estorno é só posse.** `TransactionPolicy::reverse` checa só
  `initiator_id === user->id`. "Já estornada" (`409`) e "estorno de estorno" (`422`) são regra
  de negócio revalidada sob lock pela Action — voltam como erro de domínio, não um `403`
  genérico. Quem **recebeu** uma transferência não a estorna (não é o iniciador).

- **Rotas do Fortify curadas.** `Fortify::ignoreRoutes()` + um `routes/fortify.php` que declara
  exatamente os 9 endpoints de auth em uso — sem as rotas mortas de password-confirm, 2FA e
  passkey que o Fortify registra por padrão.

### Testes

- **`DatabaseTruncation`, não `RefreshDatabase`, no que toca dinheiro.** `RefreshDatabase`
  embrulha cada teste numa transação que nunca commita — o trigger contábil (dispara no commit)
  e os eventos `DB::afterCommit` ficariam mudos.

- **Concorrência testada de verdade.** `tests/Concurrency` forka K processos, cada um com
  conexão própria, contra o Postgres real. É o que dá confiança de que o lock ordenado e a
  idempotência seguram sob corrida — não dá pra provar isso com mock.

- **Redis real nos testes.** `PublishUserEvent` publica de verdade (cliente `predis` contra o
  Redis do compose); sem Redis no ar, os testes de HTTP quebram.

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
