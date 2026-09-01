<?php

use App\Domain\Wallet\ValueObjects\Money;

it('builds from cents and exposes parts', function () {
    $m = Money::fromCents(15000);
    expect($m->cents())->toBe(15000)
        ->and($m->currency())->toBe('BRL')
        ->and($m->decimalString())->toBe('150.00');
});

it('parses decimal strings and rejects bad input', function () {
    expect(Money::fromDecimalString('150.00')->cents())->toBe(15000)
        ->and(Money::fromDecimalString('0.05')->cents())->toBe(5)
        ->and(Money::fromDecimalString('150')->cents())->toBe(15000);

    foreach (['150.005', '-1.00', 'abc', '1,00', '', "150.00\n"] as $bad) {
        expect(fn () => Money::fromDecimalString($bad))->toThrow(InvalidArgumentException::class);
    }
});

it('supports negative amounts from cents', function () {
    expect(Money::fromCents(-15000)->isNegative())->toBeTrue();
});

it('does arithmetic and comparison', function () {
    $a = Money::fromCents(10000);
    $b = Money::fromCents(3000);
    expect($a->subtract($b)->cents())->toBe(7000)
        ->and($a->add($b)->cents())->toBe(13000)
        ->and($a->greaterThanOrEqual($b))->toBeTrue()
        ->and($b->greaterThanOrEqual($a))->toBeFalse()
        ->and($a->equals(Money::fromCents(10000)))->toBeTrue();
});

it('rejects cross-currency operations', function () {
    expect(fn () => Money::fromCents(100, 'BRL')->add(Money::fromCents(100, 'USD')))
        ->toThrow(InvalidArgumentException::class);
});

it('formats pt-BR and json-serializes', function () {
    $m = Money::fromCents(15000);
    expect($m->formatBRL())->toContain('150,00')
        ->and($m->jsonSerialize())->toBe(['amount' => '150.00', 'currency' => 'BRL']);
});
