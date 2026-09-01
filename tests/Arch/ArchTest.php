<?php

arch('domain does not depend on http')
    ->expect('App\Domain\Wallet')
    ->not->toUse('App\Http');

arch('actions are final and invokable')
    ->expect('App\Domain\Wallet\Actions')
    ->toBeFinal()
    ->toHaveMethod('__invoke');

arch('domain exceptions extend the base')
    ->expect('App\Domain\Wallet\Exceptions')
    ->toExtend('App\Domain\Wallet\Exceptions\WalletDomainException')
    ->ignoring('App\Domain\Wallet\Exceptions\WalletDomainException');

arch('value objects and DTOs are readonly-ish')
    ->expect('App\Domain\Wallet\DTOs')
    ->toBeReadonly();

arch('no debug helpers leak')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('domain avoids facades for DB access')
    ->expect('App\Domain\Wallet\Actions')
    ->not->toUse('Illuminate\Support\Facades\DB');

// ---- HTTP layer (Plan 2) --------------------------------------------------

arch('the domain still does not reach into http')
    ->expect('App\Domain\Wallet')
    ->not->toUse('App\Http');

arch('controllers do not run raw SQL')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB')
    ->ignoring('App\Http\Controllers\Api\V1\SessionController'); // reads the framework `sessions` table, which has no model

arch('money-write controllers delegate to a domain action')
    ->expect('App\Http\Controllers\Api\V1\DepositController')
    ->toUse('App\Domain\Wallet\Actions\DepositFunds');

arch('form requests are form requests')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('api resources are json resources')
    ->expect('App\Http\Resources')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');

arch('middleware exposes handle()')
    ->expect('App\Http\Middleware')
    ->toHaveMethod('handle');

arch('no debug helpers leak from http')
    ->expect('App\Http')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch()->preset()->php();
