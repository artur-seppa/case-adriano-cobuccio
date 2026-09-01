<?php

namespace App\Providers;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Listeners\PublishUserEvent;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        // Declare the sections (name + description) in a deliberate order; runs
        // after Scramble's own AddDocumentTags, so this ordering wins.
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) use ($sections) {
            $openApi->tags = array_map(
                fn (string $name, string $description) => new Tag($name, $description),
                array_keys($sections),
                array_values($sections),
            );
        });
    }
}
