<?php

use App\Rules\AsMoney;

/**
 * @return list<string> the failure messages the rule produced
 */
function runAsMoney(mixed $value): array
{
    $failures = [];
    (new AsMoney)->validate('amount', $value, function (string $message) use (&$failures) {
        $failures[] = $message;
    });

    return $failures;
}

dataset('bad amounts', ['0', '0.00', '-1.00', '10.005', 'abc', '1,00', '', '10.', '.5', ' 10']);

it('accepts a well-formed positive decimal', function () {
    foreach (['150.00', '0.01', '150', '1000000.99'] as $good) {
        expect(runAsMoney($good))->toBe([], "[$good] should pass");
    }
});

it('rejects bad amounts', function (string $bad) {
    expect(runAsMoney($bad))->not->toBe([]);
})->with('bad amounts');

it('rejects a non-string value', function () {
    expect(runAsMoney(1000))->not->toBe([]);
});

it('reports the zero case with the greater-than-zero message', function () {
    expect(runAsMoney('0.00'))->toContain('The :attribute must be greater than zero.');
});
