<?php

namespace App\Providers;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Listeners\LogBusinessEvent;
use App\Domain\Wallet\Listeners\PublishUserEvent;
use App\Redis\MultiClientRedisManager;
use Closure;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap in a Redis manager that honours a per-connection `client` key, so
        // the SSE `pubsub` connection can run on predis (for pubSubLoop()) while
        // queue/cache/Horizon stay on phpredis. `extend` wins even though the
        // framework's RedisServiceProvider is deferred. See config/database.php.
        $this->app->extend('redis', function ($manager, $app) {
            $config = $app->make('config')->get('database.redis', []);

            return new MultiClientRedisManager(
                $app, Arr::pull($config, 'client', 'phpredis'), $config
            );
        });

        // Telescope is dev-only and never auto-discovered (see composer.json
        // `dont-discover`) — register it (and its gate provider) manually,
        // and only outside production, so it never boots there by accident.
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Reads: 60/min per user (spec §10.6). Falls back to IP for the rare
        // unauthenticated hit before auth:sanctum rejects it.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Money writes: 10/min per user (spec §10.6).
        RateLimiter::for('transfers', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()->id));

        // SSE stream opens: 12/min per user (spec §11).
        RateLimiter::for('stream', fn (Request $request) => Limit::perMinute(12)
            ->by($request->user()->id));

        // All Fortify auth routes (login, register, forgot/reset password, ...):
        // 5/min keyed on email+IP, IP-only when there is no email field. Blocks
        // credential stuffing, account-creation floods and forgot-password abuse.
        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = $email !== ''
                ? mb_strtolower($email).'|'.$request->ip()
                : (string) $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Post-commit realtime fan-out (spec §6.6 / §11).
        Event::listen(FundsDeposited::class, [PublishUserEvent::class, 'handleDeposited']);
        Event::listen(FundsTransferred::class, [PublishUserEvent::class, 'handleTransferred']);
        Event::listen(TransactionReversed::class, [PublishUserEvent::class, 'handleReversed']);

        Event::listen(FundsDeposited::class, [LogBusinessEvent::class, 'handleDeposited']);
        Event::listen(FundsTransferred::class, [LogBusinessEvent::class, 'handleTransferred']);
        Event::listen(TransactionReversed::class, [LogBusinessEvent::class, 'handleReversed']);

        Gate::define('viewPulse', function ($user = null) {
            return ! $this->app->environment('production')
                || in_array(optional($user)->email, config('horizon.dashboard_emails', []), true);
        });

        // Financial app: passwords are at least 10 chars, mixed case + a digit.
        Password::defaults(fn () => app()->isProduction()
            ? Password::min(10)->mixedCase()->numbers()
            : Password::min(10));

        $this->configureApiDocs();
    }

    /**
     * The OpenAPI documentation (`/docs/api`): the access gate, and grouping every
     * endpoint into a readable section (Autenticação, Carteira, Depósitos, ...)
     * instead of one flat list.
     */
    private function configureApiDocs(): void
    {
        // Open everywhere except production, where an authenticated user is required.
        Gate::define('viewApiDocs', fn ($user = null) => ! app()->environment('production') || $user !== null);

        $sections = [
            'Autenticação' => 'Cadastro, login/logout, verificação de e-mail e reset de senha (sessão via cookie Sanctum).',
            'Carteira' => 'Saldo e extrato (ledger) da carteira do usuário.',
            'Transações' => 'Listagem e detalhe das transações em que o usuário é iniciador ou contraparte.',
            'Depósitos' => 'Crédito interno na carteira — soma ao saldo mesmo se negativo.',
            'Transferências' => 'Movimentação de saldo entre usuários, validando saldo suficiente.',
            'Estornos' => 'Reversão de uma operação própria (lançamentos espelhados, imutável).',
            'Sessões' => 'Sessões ativas do usuário e revogação individual.',
            'Tempo real' => 'Stream SSE de eventos da carteira (Server-Sent Events).',
        ];

        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo) {
            $uri = $routeInfo->route->uri();

            return [match (true) {
                Str::startsWith($uri, 'api/v1/wallet/sessions') => 'Sessões',
                Str::startsWith($uri, 'api/v1/wallet') => 'Carteira',
                Str::contains($uri, '/reversal') => 'Estornos',
                Str::startsWith($uri, 'api/v1/deposits') => 'Depósitos',
                Str::startsWith($uri, 'api/v1/transfers') => 'Transferências',
                Str::startsWith($uri, 'api/v1/transactions') => 'Transações',
                Str::startsWith($uri, 'api/v1/stream') => 'Tempo real',
                default => 'Autenticação',
            }];
        });

        // Registered after Scramble's own extensions (which run during its
        // bootingPackage) so these transformers get the last word.
        $this->app->booted(function () use ($sections) {
            Scramble::configure()
                // Declare the sections (name + description) in a deliberate order.
                ->withDocumentTransformers(function (OpenApi $openApi) use ($sections) {
                    $openApi->tags = array_map(
                        fn (string $name, string $description) => new Tag($name, $description),
                        array_keys($sections),
                        array_values($sections),
                    );
                })
                // Fortify's auth endpoints validate inside actions/closures, so
                // Scramble can't infer their bodies — declare them by hand with
                // real example values.
                ->withOperationTransformers($this->authRequestBodyDocs());
        });
    }

    /**
     * @return Closure(Operation, RouteInfo): void
     */
    private function authRequestBodyDocs(): Closure
    {
        $bodies = [
            'api/login' => [
                'required' => ['email', 'password'],
                'fields' => [
                    'email' => ['string', 'email', 'alice@wallet.test'],
                    'password' => ['string', 'password', 'Password1234'],
                    // `remember` is optional — persistent "remember me" cookie.
                    'remember' => ['bool', null, true],
                ],
                'success' => [204, 'Session started (Set-Cookie).'],
            ],
            'api/register' => [
                'required' => ['name', 'email', 'password', 'password_confirmation'],
                'fields' => [
                    'name' => ['string', null, 'Alice Souza'],
                    'email' => ['string', 'email', 'alice@wallet.test'],
                    'password' => ['string', 'password', 'Password1234'],
                    'password_confirmation' => ['string', 'password', 'Password1234'],
                ],
                'success' => [201, 'User + wallet created; body: { user, requires_email_verification }.'],
            ],
            'api/forgot-password' => [
                'required' => ['email'],
                'fields' => [
                    'email' => ['string', 'email', 'alice@wallet.test'],
                ],
                'success' => [200, 'Reset link e-mailed; body: { message }.'],
            ],
            'api/reset-password' => [
                'required' => ['token', 'email', 'password', 'password_confirmation'],
                'fields' => [
                    'token' => ['string', null, '8b1f0c2d3e4a5b6c7d8e9f0a1b2c3d4e'],
                    'email' => ['string', 'email', 'alice@wallet.test'],
                    'password' => ['string', 'password', 'NovaSenha1234'],
                    'password_confirmation' => ['string', 'password', 'NovaSenha1234'],
                ],
                'success' => [204, 'Password changed.'],
            ],
            // Logged-in self-service (PUT). Fortify validates inside the actions,
            // so Scramble sees no body here either.
            'api/user/password' => [
                'required' => ['current_password', 'password', 'password_confirmation'],
                'fields' => [
                    'current_password' => ['string', 'password', 'Password1234'],
                    'password' => ['string', 'password', 'NovaSenha1234'],
                    'password_confirmation' => ['string', 'password', 'NovaSenha1234'],
                ],
                'success' => [204, 'Password changed.'],
            ],
            'api/user/profile-information' => [
                'required' => ['name', 'email'],
                'fields' => [
                    'name' => ['string', null, 'Alice Souza'],
                    'email' => ['string', 'email', 'alice@wallet.test'],
                ],
                'success' => [204, 'Profile updated (changing the e-mail resets verification).'],
            ],
        ];

        return function (Operation $operation, RouteInfo $routeInfo) use ($bodies) {
            $spec = $bodies[$routeInfo->route->uri()] ?? null;

            if ($spec === null) {
                return;
            }

            $object = new ObjectType;
            foreach ($spec['fields'] as $name => [$kind, $format, $example]) {
                $type = $kind === 'bool' ? new BooleanType : new StringType;
                if ($format !== null) {
                    $type->format($format);
                }
                $object->addProperty($name, $type->example($example));
            }
            $object->setRequired($spec['required']);

            $operation->addRequestBodyObject(
                RequestBodyObject::make()
                    ->required()
                    ->setContent('application/json', Schema::fromType($object)),
            );

            // Scramble can't infer Fortify's response — replace its guessed 200
            // with the real success code + a 422 (all four routes validate).
            [$code, $description] = $spec['success'];
            $operation->responses = [];
            $operation->addResponse(Response::make($code)->description($description));
            $operation->addResponse(
                Response::make(422)->description('Validation failed (application/problem+json).'),
            );
        };
    }
}
