<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the API money-input contract: a decimal string with at most two
 * fraction digits, strictly greater than zero. The value is never cast to
 * float — it is later parsed by Money::fromDecimalString().
 */
class AsMoney implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d+(\.\d{1,2})?$/D', $value) !== 1) {
            $fail('The :attribute must be a decimal amount with up to two decimal places.');

            return;
        }

        // Format is already validated; "all zeros" (0, 0.0, 0.00) is the only
        // non-positive case the regex admits. No float cast.
        if (preg_match('/^0+(\.0{1,2})?$/D', $value) === 1) {
            $fail('The :attribute must be greater than zero.');
        }
    }
}
