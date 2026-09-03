# Carteira financeira

API de uma carteira financeira: cadastro e autenticação, depósito, transferência entre
usuários e reversão de qualquer operação. Saldo pode ficar negativo (é um estado válido);
todo depósito e toda transferência são estornáveis, automaticamente por inconsistência ou a
pedido do usuário.

Laravel 12 como API pura (`/api/v1`) sobre PostgreSQL 16 e Redis 7, servida por
FrankenPHP/Octane. O frontend Next.js 15 vive em `frontend/`. A stack Docker completa (app,
web, nginx, Postgres, Redis, Horizon, Pulse, scheduler, Mailpit) sobe com um comando.

---

## Arquitetura

**Ledger contábil double-entry.** O saldo não é um número solto numa coluna: é a soma de
lançamentos imutáveis (`ledger_entries`). Toda operação de dinheiro é uma `transaction` que
gera **≥ 2 lançamentos** cujo somatório é zero (o que sai de uma carteira entra em outra).
`wallets.balance_cents` é um cache materializado, atualizado sob lock; a verdade é
`SUM(±ledger_entries)`. `wallet:reconcile` prova periodicamente que os dois batem e que a
soma global é zero.

**Conta-sistema `external_world`.** Depósito não tem gateway externo: é crédito interno. Para
o ledger fechar, o depósito **debita** a carteira-sistema `external_world` e credita o
usuário. O saldo (bem negativo) dela é o total já injetado no sistema.

**Dinheiro nunca é float.** `bigint` de centavos no banco, `moneyphp/money` num Value Object
`Money` imutável na aplicação, string decimal (`"150.00"`) no payload da API. Moeda única
`BRL`: o código valida igualdade, nunca converte.

**Arquitetura em camadas, domínio isolado do HTTP.** Sem repository, sem hexagonal completo:
os models são Eloquent. Mas o domínio (`app/Domain/Wallet`) não conhece a camada de entrega:
um teste em `tests/Arch` proíbe qualquer classe de `App\Domain\Wallet` de importar `App\Http`,
as Actions são `final` + `__invoke` e os DTOs são `readonly`. Um `__invoke` por caso de uso
(`DepositFunds`, `TransferFunds`, `ReverseTransaction`). Um único `LedgerPoster` escreve
`transactions` + `ledger_entries` e muta `wallets`. Controllers são finos: traduzem HTTP ⇄ DTO
e delegam à Action.

**Autenticação por sessão** (Fortify headless + Sanctum stateful): cookie `HttpOnly` +
CSRF, mesma origem, sem token no JavaScript, sessão revogável na hora. `personal_access_tokens`
existe mas não é usada no fluxo atual.

**Erros RFC 9457** (`application/problem+json`): contrato estável, sem vazar detalhe interno.
Tratamento central em `bootstrap/app.php`; um `ProblemMapper` cobre cada exceção e a mapeia
para um status.

**IDs ULID** (`char(26)`) em todo o domínio: k-sortable e não enumeráveis nas rotas.

**Tempo real por SSE** (não WebSocket): quando dinheiro se move, um listener publica um
"nudge" compacto no canal Redis do usuário; `GET /api/v1/stream` encaminha como
`text/event-stream`. O cliente sempre re-busca os números; o payload é só um gatilho.

### Garantias de consistência

| Onde | O quê |
|---|---|
| Aplicação | `DB::transaction(attempts: 3)`, lock pessimista (`lockForUpdate`) nas carteiras em **ordem determinística por `id`** (evita deadlock), assert Σdébito = Σcrédito antes do commit |
| Postgres | `CHECK` de sinal/valor/identidade, índices únicos parciais (1 estorno por transação, `idempotency_key` única), e um **`CONSTRAINT TRIGGER ... DEFERRABLE INITIALLY DEFERRED`** que rejeita no commit qualquer transação desbalanceada |
| Idempotência | **Camada A:** middleware `EnsureIdempotency` + tabela `idempotency_keys` (replay da resposta `2xx`, `409` em corrida, `422` em key reusada com corpo diferente). **Camada B:** coluna `transactions.idempotency_key` única, backstop estrutural para qualquer caller (console, fila, testes) |
| Reconciliação | `wallet:reconcile` (comando, agendado e no boot do container): compara cache × ledger × último snapshot, e a soma global |

---

## Rodando com Docker (stack completa)

```bash
cp .env.example .env      # já vem pronto para o compose
make up                   # sobe app (FrankenPHP/Octane), web (Next), nginx, postgres, redis, mailpit, worker, pulse, scheduler
```

Abrir `http://localhost`. Usuários de demo: `make fresh` (roda o `DemoSeeder`).

| | |
|---|---|
| App | `http://localhost` |
| Contrato da API | `http://localhost/docs/api` |
| Health | `http://localhost/health` · `http://localhost/up` |
| Pulse (saúde agregada) | `http://localhost/pulse` |
| Horizon (fila) | `http://localhost/horizon` |
| Telescope (forense, só local) | `http://localhost/telescope` |
| Métricas Prometheus | não exposto pelo proxy público; `docker compose exec app curl localhost:8000/metrics` (rede interna) |
| Mailpit | `http://localhost:8025` |

Sem TLS de propósito, para zerar o setup local. Em deploy real, TLS termina no proxy/load
balancer; setar `SESSION_SECURE_COOKIE=true`, `APP_ENV=production` (dashboards passam a exigir
e-mail em `HORIZON_DASHBOARD_EMAILS`, e o Telescope não carrega porque seu registro no
`AppServiceProvider` é condicionado a `environment('local')`), e ajustar
`SANCTUM_STATEFUL_DOMAINS`/`APP_URL` para o domínio real.

O link de reset de senha aponta para o SPA (`FRONTEND_URL`), em
`/reset-password?token=...&email=...`; pegue o `token` da URL no Mailpit e chame
`POST /api/reset-password` direto se preferir sem UI.

Em `APP_ENV=local`, o `--seed` do `make fresh` também roda o **`DemoSeeder`**: 3 usuários
verificados (`alice@wallet.test`, `bruno@wallet.test`, `carla@wallet.test`, senha `Password1234`),
cada um com carteira financiada, 3 transferências entre eles e 1 estorno, tudo pelas Actions
reais, com o `wallet:reconcile` passando em seguida. Roda também isolado com
`docker compose exec app php artisan db:seed --class="Database\Seeders\DemoSeeder"`; nunca em
produção.

### Testes

A suíte roda no **host** (não em Docker), contra o Postgres/Redis publicados pelo próprio
`compose.yml` (`127.0.0.1:5442`/`127.0.0.1:6389`, as mesmas portas de `.env.testing`); o
`make up` já deixa isso pronto.

```bash
php artisan test             # unit + integração + HTTP + concorrência + arquitetura
./vendor/bin/pint --test     # estilo (preset Laravel)
php artisan wallet:reconcile # invariante contábil: exit 0 saudável, 1 drift
```

Ou via `make`: `up` · `down` · `fresh` · `test` · `pint` · `logs` · `shell` · `dtest` ·
`dreconcile` · `serve` · `reconcile` · `schedule` · `docs` · `routes` (os últimos cinco
assumem um PHP local com Postgres/Redis próprios, fora do fluxo Docker documentado aqui).

A suíte usa um banco dedicado (`wallet_test`), PostgreSQL real e nunca SQLite: o trigger
contábil só dispara num commit de verdade. `tests/Concurrency` forka processos reais com
`pcntl` (pulada se a extensão não existir) e valida, sob concorrência, ausência de
duplo-gasto, corrida de idempotência, estorno concorrente da mesma transação, e ausência de
deadlock em transferências cruzadas.

---

## API

Base `/api/v1`, JSON only, atrás de `auth:sanctum`. Escritas de dinheiro exigem o header
`Idempotency-Key: <uuid>` (ausência → `400`) e passam por `verified` + rate limit. Toda
resposta carrega `X-Request-Id`. `GET /` devolve um índice JSON com os links de docs/health.

Auth é por **sessão** (cookie `HttpOnly` do Sanctum, mesma origem), não bearer token. Toda
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
| `GET /api/v1/wallet/statement` | extrato: `ledger_entries`, paginado por cursor via `sequence` |
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
| `php artisan scramble:export --path=openapi.json` | exporta o mesmo documento para arquivo |

O contrato é derivado **mecanicamente** dos Form Requests e Resources; não há anotação a
manter em dia. Os endpoints são agrupados em seções na UI (Autenticação, Carteira, Transações,
Depósitos, Transferências, Estornos, Sessões, Tempo real). Fora de `local`, `/docs/*` fica
atrás do gate `viewApiDocs`.

---

## Observabilidade

| Recurso | Como checar |
|---|---|
| Health check | `curl -i http://localhost/health` → `200 {"status":"ok","db":"ok","redis":"ok"}` (503 se algum componente cair); `http://localhost/up` é o smoke-check nativo do Laravel |
| Índice de serviço | `curl http://localhost/` → JSON com links de `docs`, `openapi`, `health` |
| `X-Request-Id` | `curl -i http://localhost/up \| grep -i x-request-id`. Toda resposta carrega o id de correlação (aceita o do cliente ou gera); volta no corpo de erro como `request_id`, lido do `Context` (per-request sob Octane, nunca do container) |
| Logs estruturados | JSON de uma linha por entrada em `stderr` (`LOG_CHANNEL=stderr`, `docker compose logs app`). Inclui `request.completed` por request e os eventos de negócio (`funds.deposited`, `funds.transferred`, `transaction.reversed`) |
| Erros `problem+json` | bugs/invariantes violadas (`UnbalancedLedgerException`, exceção inesperada) → `500` genérico + `Log::critical` com contexto e `request_id`; nunca vazam detalhe interno |
| **Pulse** | `http://localhost/pulse`: saúde agregada (requests, jobs, exceptions, drift do reconcile via `Pulse::set`) |
| **Horizon** | `http://localhost/horizon`: fila Redis (`mail`/`default`), jobs recentes, throughput |
| **Telescope** (só `local`) | `http://localhost/telescope`: requests, queries, jobs, eventos, cache; nunca registra fora de `environment('local')` |
| **`/metrics`** (Prometheus) | `docker compose exec app curl localhost:8000/metrics`. Não exposto pelo nginx público de propósito (o NAT do Docker faz o host aparecer como o gateway da bridge, dentro de qualquer allowlist de CIDR "interno" sensata; um Prometheus real entra na rede `wallet` e faz scrape de `app:8000/metrics` direto). 9 séries: `wallet_transactions_total`, `wallet_transaction_amount_cents`, `wallet_reversals_total`, `wallet_reconcile_drift_cents`, `wallet_reconcile_last_run_timestamp`, `wallet_insufficient_funds_total`, `wallet_idempotency_replays_total`, `wallet_http_server_request_duration_seconds`, `wallet_sse_active_connections` |
| Invariante contábil | `php artisan wallet:reconcile`: exit ≠ 0 e `Log::critical` em drift. Roda no boot do container `app`, a cada 15 min pelo scheduler, e no CI sobre dados semeados |
| Limpeza de idempotência | `php artisan idempotency:prune`: remove `idempotency_keys` expiradas; agendado de hora em hora |

Agendamento em `routes/console.php` (`reconcile` 15min, `prune` 1h, `pulse:check` 1min); no
Docker, o serviço `scheduler` (`schedule:work`) cuida disso.

Dashboards abrem sem allowlist em `APP_ENV=local` (a stack Docker default). Em produção real:
`viewHorizon`/`viewPulse` exigem e-mail em `HORIZON_DASHBOARD_EMAILS`, e Telescope simplesmente
não registra.

---

## Insights de código

### Modelagem de dados

- **Dinheiro é `bigint` de centavos, nunca float.** Coluna `bigint` **signed** no Postgres (a
  carteira precisa de saldo negativo), Value Object `Money` imutável (`moneyphp/money`) na
  aplicação, string decimal (`"150.00"`) no payload. Aritmética sempre inteira; moeda única
  `BRL`, com o código validando igualdade, nunca convertendo. Até a regra `AsMoney` checa
  `> 0` por regex, sem `(float)`.

- **PKs ULID (`char(26)`).** K-sortable como um auto-increment (bom para localidade de
  índice), mas não enumerável nas rotas: não dá para chutar `/transactions/5`. Gera o id antes
  do insert, sem round-trip.

- **`transactions` e `ledger_entries` são append-only.** A aplicação **nunca** faz
  `UPDATE`/`DELETE` nelas. Estorno é uma transação **nova** do tipo `reversal`, com lançamentos
  espelhados, ligada à original por `reversal_of_transaction_id`. Só `wallets.balance_cents` e
  `entry_count` são mutáveis.

- **`sequence`: contador monotônico por carteira** nos lançamentos (`UNIQUE (wallet_id,
  sequence)`, sem buraco). Dá ordem determinística mesmo com timestamps empatados, torna o
  `balance_after` inequívoco e é a âncora da paginação por cursor do extrato.

- **`balance_after_cents` gravado em cada lançamento.** O extrato mostra saldo corrente sem
  recomputar, e a checagem rápida da reconciliação (snapshot × cache) vira O(1).

- **Conta-sistema `external_world`.** Depósito não tem gateway externo: debita essa carteira,
  credita o usuário. O saldo (bem negativo) dela = total já injetado no sistema. É o que faz o
  double-entry fechar sem uma contraparte real.

### Concorrência e consistência

- **Lock pessimista com ordem determinística.** `SELECT … FOR UPDATE` nas linhas das carteiras
  envolvidas, **sempre** ordenadas por `id` ascendente. Transfer A→B e B→A concorrentes pegam
  os locks na mesma ordem, logo sem deadlock. Otimista (versão + retry) foi descartado:
  carteira popular geraria retry demais.

- **`DB::transaction(attempts: 3)`.** Deadlock (`40P01`) e serialization failure (`40001`)
  reexecutam a closure com backoff + jitter. Esgotou → `503` + `Retry-After`. Efeito externo
  (Redis, e-mail) **fora** da closure, sempre pós-commit (`DB::afterCommit`).

- **Trigger contábil deferido.** `CONSTRAINT TRIGGER … DEFERRABLE INITIALLY DEFERRED` roda a
  checagem Σdébito = Σcrédito **no commit**, então os lançamentos podem entrar em qualquer
  ordem dentro da transação. A aplicação também faz o assert antes (mensagem melhor, falha mais
  cedo); o trigger é o backstop que torna um ledger desbalanceado impossível de persistir.

- **Isolamento `READ COMMITTED`** (default do Postgres). Suficiente porque o lock pessimista é
  explícito; sem `REPEATABLE READ`/`SERIALIZABLE`.

- **Timeouts por request.** `SET lock_timeout='3s'` / `statement_timeout='5s'` só nos guards
  `web`/`api` (nunca console/fila, porque migration e reconcile precisam de mais). Reaplicado a
  cada request, seguro sob Octane.

- **Idempotência em duas camadas.** Middleware `EnsureIdempotency` + tabela `idempotency_keys`
  (grava a resposta e faz replay; `409` em corrida, `422` em key reusada com corpo diferente,
  `400` sem header) sobre um `UNIQUE` em `transactions.idempotency_key` (backstop estrutural
  para qualquer caller: console, fila, teste). A escrita em `idempotency_keys` é transação
  curta própria: linha `locked` antes do trabalho; ao fim, só uma resposta **2xx** vira
  `completed` (com corpo, status e `transaction_id`) e passa a ser replayada. Qualquer outra
  coisa (a Action lançou, `4xx` de validação, `5xx`) apaga a linha, para um retry na mesma key
  rodar de novo do zero em vez de fossilizar uma rejeição obsoleta com o `request_id` errado.

### Reconciliação

- **`wallet:reconcile` prova quatro coisas sobre o saldo.** Para cada carteira, `balance_cents`
  (o cache lido pela API) == `SUM(±ledger_entries)` (a verdade, recalculada do zero) ==
  `balance_after_cents` do lançamento de maior `sequence` (o snapshot gravado no posting). E,
  globalmente, `SUM(todas wallets.balance_cents) == 0` (o double-entry fecha em zero, contando
  a `external_world`).

- **Cada comparação pega uma falha diferente.** Cache × verdade denuncia um `balance_cents`
  que não foi atualizado (ou foi pela metade) sob lock. Verdade × snapshot denuncia um
  `balance_after_cents` escrito errado ou um `sequence` fora de ordem. Soma global ≠ 0 denuncia
  uma transação que persistiu com pernas desbalanceadas, algo que o trigger deferido deveria
  impedir: a reconciliação é a checagem independente que não confia no trigger.

- **Custo.** A varredura recalcula o `SUM` de cada carteira (chunk de 500), então é O(número
  de lançamentos), não O(1). Roda fora do caminho de request, no `scheduler`.

- **Quando roda.** No boot do container `app` (uma passada logo após migrate/seed, para o
  `docker compose logs app` já mostrar a invariante de pé), a cada 15 min pelo `scheduler`, e
  no CI sobre os dados do `DemoSeeder`.

- **O que faz com um drift.** `Log::critical('wallet.reconcile.drift', …)` com o payload
  completo, move o gauge `wallet_reconcile_drift_cents`, atualiza `Pulse::set`, e sai com
  código ≠ 0. Não toma ação corretiva sozinho: `--fix` (manual) reescreve o cache a partir do
  ledger, e existe assim de propósito, porque consertar automático mascararia a causa raiz.

- **Ponto cego conhecido.** O gauge exporta a soma global, então um alerta em
  `wallet_reconcile_drift_cents != 0` pega o caso comum mas não um drift compensado (carteira A
  +100, carteira B −100, soma global ainda 0). Esse só aparece na comparação por carteira, no
  `Log::critical` e no exit code; um gauge de contagem de carteiras divergentes fecharia a
  lacuna.

### Fila e agendamento

- **`PublishUserEvent` é síncrono, pós-commit.** É só um `Redis::publish` barato para o SSE,
  não vale uma fila. Envolvido em `try/catch`: Redis fora do ar não transforma um depósito que
  **commitou** num `500`. Os listeners de e-mail/audit/métrica é que iriam para a fila (Redis +
  Horizon, no empacotamento Docker).

- **E-mail vai para a fila `mail` do Horizon.** Verificação e reset de senha são `ShouldQueue`
  (`QueuedVerifyEmail`, `QueuedResetPassword`), drenados pelo container `worker`. `tries: 3`
  com backoff escalonado (60s, depois 300s) no supervisor, para um `4xx` transitório de SMTP
  (greylisting, rate limit) não queimar as tentativas em milissegundos. Estourou as 3 →
  `failed_jobs`, visível no Horizon.

- **Scheduler em `routes/console.php`.** `wallet:reconcile` (15 min) e `idempotency:prune`
  (horário). Precisa de um `schedule:work` (ou cron) rodando; no Docker, o container
  `scheduler`.

### Tempo real (SSE)

- **Um generator PHP segura a conexão.** `GET /api/v1/stream` faz `SUBSCRIBE` no canal
  `user-events:{id}` (o id vem da sessão, nunca de query param) e encaminha cada nudge como
  frame SSE. O `pubSubLoop` do `predis` usa um `read_write_timeout` curto (5s), então o loop
  acorda de tempos em tempos para checar `connection_aborted()` e mandar um heartbeat (`event:
  ping`) a cada 20s.

- **`set_time_limit(0)` no controller.** O Octane roda o FrankenPHP com
  `REQUEST_MAX_EXECUTION_TIME` (30s, de `config/octane.php`), que cortaria a conexão SSE a cada
  30s e faria o `EventSource` do browser ficar religando. O controller zera esse limite só para
  essa request; o heartbeat e o `connection_aborted()` são o que de fato limitam a vida do
  stream.

- **No máximo 3 streams simultâneos por usuário.** Um contador em cache (`Cache::add` +
  `Cache::increment`, TTL de 900s) barra o 4º com `429`. Liberado no `finally` do generator,
  com `register_shutdown_function` como rede para um timeout/fatal que pule o `finally`.

- **O cliente não pisca a cada reconexão.** O `EventSource` cai e reconecta como rotina (worker
  reciclado, blip de rede) e se cura em poucos segundos. O banner "Reconectando" só aparece se
  a queda passar de ~2,5s; a escalada para "sem conexão" continua em 15s.

### API e HTTP

- **Paginação por cursor.** O extrato (`/wallet/statement`) pagina por `sequence` desc, não
  offset. `WHERE sequence < :ultimo ORDER BY sequence DESC LIMIT n` bate direto no índice, O(1)
  por página, e é estável sob inserção concorrente (lançamento novo entra no topo, nunca
  desloca as páginas). `/transactions` usa o mesmo esquema com `(created_at, id)`. Sem `total`,
  que é o trade-off do cursor.

- **Erros RFC 9457 (`application/problem+json`).** Um `ProblemMapper` central mapeia cada
  exceção para um status, com `type` URI estável. Bug/invariante violada → `500` genérico +
  `Log::critical` com contexto e `request_id`; nunca vaza detalhe interno. Idempotência
  (400/409/422) responde direto no middleware.

- **`X-Request-Id` em toda resposta** (inclusive erro). `AssignRequestId` é **global**: o spec
  exige o header até no `/up`, que não passa por grupo de middleware. O id volta no corpo do
  erro (`request_id`) e entra no `Context` do log.

- **`isReversed()` prefere a relação eager-loaded.** "Está estornada?" = existe transação com
  `reversal_of_transaction_id` apontando para ela. Em listagem seria N+1; o método usa a
  relação carregada quando disponível, só cai para `->exists()` fora de contexto de lista.

- **Política de estorno é só posse.** `TransactionPolicy::reverse` checa só
  `initiator_id === user->id`. "Já estornada" (`409`) e "estorno de estorno" (`422`) são regra
  de negócio revalidada sob lock pela Action; voltam como erro de domínio, não um `403`
  genérico. Quem **recebeu** uma transferência não a estorna (não é o iniciador).

- **Rotas do Fortify curadas.** `Fortify::ignoreRoutes()` + um `routes/fortify.php` que declara
  exatamente os 9 endpoints de auth em uso, sem as rotas mortas de password-confirm, 2FA e
  passkey que o Fortify registra por padrão.

### Testes

- **Banco de teste dedicado, PostgreSQL real.** `wallet_test` (`.env.testing`), nunca SQLite:
  o trigger contábil e os hooks `DB::afterCommit` só disparam num commit de verdade. O que toca
  dinheiro usa `DatabaseTruncation`, não `RefreshDatabase` (que embrulha cada teste numa
  transação sem commit, deixando trigger e eventos mudos).

- **Factories montam o estado.** `User::factory()`, `Wallet::factory()->forUser()` e helpers
  como `transferPair()` (factory + `DepositFunds` real para financiar a carteira) compõem o
  cenário; os testes batem nas Actions e rotas reais, não em stubs.

- **Mock só na borda.** No backend quase não há: `Notification::fake()` nos testes de e-mail e
  `Redis::shouldReceive('publish')` num teste de stream. O `PublishUserEvent` publica de
  verdade (cliente `predis` contra o Redis do compose); sem Redis no ar, os testes de HTTP
  quebram. No frontend, o MSW intercepta o `fetch` na camada de rede com respostas HTTP reais
  (status, headers, corpo `problem+json`), então `apiFetch` e o TanStack Query rodam de
  verdade.

- **Concorrência testada de verdade.** `tests/Concurrency` forka K processos, cada um com
  conexão própria, contra o Postgres real. É o que dá confiança de que o lock ordenado e a
  idempotência seguram sob corrida; não dá para provar isso com mock.

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

`.github/workflows/backend.yml` sobe Postgres 16 + Redis 7 como service containers e roda, em
ordem: `pint --test`, `migrate`, `php artisan test` (suíte inteira, incl. concorrência),
`wallet:reconcile` sobre dados semeados, e `scramble:export` (publica `openapi.json` como
artefato). Sem gate de cobertura; coverage é gerável localmente com
`php artisan test --coverage`.

`.github/workflows/frontend.yml` roda em paralelo: `npm ci`, `lint`, `typecheck`, `vitest`, e
`api:types` com checagem de drift contra o `openapi.json` commitado. Os dois workflows juntos
são os "2 jobs enxutos" do spec §14; sem gate de cobertura em nenhum dos dois.

## Frontend

Next.js 15 (App Router), client-first: TypeScript, Tailwind v4, TanStack Query, `nuqs`,
MSW+Vitest. Organização feature-based: cada feature em `src/features/<nome>/` com seu próprio
`api/`, `hooks/` e `components/`; o transversal (cliente HTTP, `queryClient`, UI, realtime)
fica em `src/shared/`, e `src/app/` é só o roteamento do Next. Vive em `frontend/`, código
próprio, não compartilha nada do Vite/Blade legado da raiz (mantido só porque o scaffold do
Laravel o criou; sem uso desde que a `resources/views/welcome.blade.php` saiu).

### Cache e frescor

O `QueryClient` default só fixa política de retry (não retenta um `ApiError`, senão até 2x) e
`refetchOnWindowFocus: false`. O `staleTime` é por query, calibrado ao recurso:

- `useWallet`: `staleTime` 60s, `gcTime` 5min, `refetchOnWindowFocus: true`, e
  `refetchInterval` de 30s **só quando o SSE está `down`** (polling é o fallback, não o caminho
  normal).
- `useSession`: `staleTime: Infinity`; a sessão só é revalidada por invalidação explícita
  (login, logout, registro).
- `useRecentTransactions`: `staleTime` 30s.

O frescor de verdade vem do SSE: quando dinheiro se move, o `RealtimeProvider` invalida
`['wallet']` e `['transactions']`. O `staleTime` é o backstop para quando o stream cai.

### Rodando local

```bash
# terminal 1: backend (múltiplos workers: obrigatório)
PHP_CLI_SERVER_WORKERS=10 php artisan serve --no-reload   # :8000

# terminal 2: frontend
cd frontend
cp .env.local.example .env.local
npm install
npm run dev          # :3000
```

> **`php artisan serve` sozinho trava o app.** O servidor embutido do PHP é single-process;
> assim que uma sessão autenticada abre, o `RealtimeProvider` do frontend mantém uma conexão
> SSE (`GET /api/v1/stream`) aberta o tempo todo, e essa conexão longa ocupa o único worker,
> então toda outra request (extrato, detalhe, depósito…) fica presa até dar timeout / 500.
> `PHP_CLI_SERVER_WORKERS=10 --no-reload` forka workers e resolve. Na stack Docker o runtime é
> FrankenPHP/Octane, multi-worker por natureza, então o problema não existe lá.

Abrir `http://localhost:3000`. O `next.config.ts` faz proxy de `/api`, `/sanctum` e `/docs`
para o backend em `:8000`; é isso que faz o cookie de sessão do Sanctum (`SameSite=Lax`)
funcionar sem CORS cross-origin em dev. Na stack Docker o nginx ocupa esse papel e o proxy do
Next fica inerte (`rewrites()` só roda com `NODE_ENV=development`).

### Tipos da API

```bash
php artisan scramble:export --path=openapi.json   # na raiz, com o backend presente
cd frontend && npm run api:types                  # regenera src/shared/api/generated/api.d.ts
```

Rodar sempre que um endpoint mudar de forma; o CI (`frontend.yml`) falha se os dois saírem de
sincronia.

### Testes

```bash
cd frontend
npm run test         # Vitest + Testing Library + MSW
npm run lint
npm run typecheck
```

Vitest + Testing Library nos componentes e hooks, com o MSW mockando o HTTP na borda (handlers
padrão em `src/shared/testing/`, cada teste sobrescreve o que precisa). Não há E2E de browser;
a cobertura para no nível de componente.
