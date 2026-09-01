<?php

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

abstract class WalletDomainException extends RuntimeException
{
    /** @return array<string, scalar> */
    abstract public function context(): array;
}
