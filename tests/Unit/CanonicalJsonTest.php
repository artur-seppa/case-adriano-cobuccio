<?php

use App\Domain\Wallet\Support\CanonicalJson;

it('produces the same fingerprint regardless of key order', function () {
    $a = CanonicalJson::fingerprint(['amount' => '10.00', 'currency' => 'BRL']);
    $b = CanonicalJson::fingerprint(['currency' => 'BRL', 'amount' => '10.00']);

    expect($a)->toBe($b)->and(strlen($a))->toBe(64);
});

it('changes when a value changes', function () {
    expect(CanonicalJson::fingerprint(['amount' => '10.00']))
        ->not->toBe(CanonicalJson::fingerprint(['amount' => '10.01']));
});

it('normalizes nested structures', function () {
    $a = CanonicalJson::fingerprint(['meta' => ['b' => 1, 'a' => 2]]);
    $b = CanonicalJson::fingerprint(['meta' => ['a' => 2, 'b' => 1]]);

    expect($a)->toBe($b);
});

it('treats an empty payload deterministically', function () {
    expect(CanonicalJson::fingerprint([]))->toBe(CanonicalJson::fingerprint([]));
});
