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

arch()->preset()->php();
