<?php

namespace App\Domain\Wallet\ValueObjects;

use JsonSerializable;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money as BaseMoney;
use Money\Parser\DecimalMoneyParser;
use NumberFormatter;

final class Money implements JsonSerializable
{
    private function __construct(private readonly BaseMoney $money) {}

    public static function fromCents(int $cents, string $currency = 'BRL'): self
    {
        return new self(new BaseMoney($cents, new Currency($currency)));
    }

    public static function fromDecimalString(string $amount, string $currency = 'BRL'): self
    {
        if (preg_match('/^\d+(\.\d{1,2})?$/D', $amount) !== 1) {
            throw new \InvalidArgumentException("Invalid money amount: [{$amount}]");
        }

        $parser = new DecimalMoneyParser(new ISOCurrencies);

        return new self($parser->parse($amount, new Currency($currency)));
    }

    public function cents(): int
    {
        return (int) $this->money->getAmount();
    }

    public function currency(): string
    {
        return $this->money->getCurrency()->getCode();
    }

    public function decimalString(): string
    {
        $formatted = (new DecimalMoneyFormatter(new ISOCurrencies))->format($this->money);

        // DecimalMoneyFormatter emits "150" for whole amounts and "-5.4" etc.
        // Normalise to always have exactly 2 decimal places.
        if (! str_contains($formatted, '.')) {
            return $formatted.'.00';
        }

        [$int, $frac] = explode('.', $formatted, 2);

        return $int.'.'.str_pad($frac, 2, '0');
    }

    public function formatBRL(): string
    {
        $formatter = new IntlMoneyFormatter(
            new NumberFormatter('pt_BR', NumberFormatter::CURRENCY),
            new ISOCurrencies,
        );

        return $formatter->format($this->money);
    }

    public function add(self $other): self
    {
        return new self($this->money->add($other->money));
    }

    public function subtract(self $other): self
    {
        return new self($this->money->subtract($other->money));
    }

    public function isNegative(): bool
    {
        return $this->money->isNegative();
    }

    public function isZero(): bool
    {
        return $this->money->isZero();
    }

    public function isPositive(): bool
    {
        return $this->money->isPositive();
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->money->greaterThanOrEqual($other->money);
    }

    public function equals(self $other): bool
    {
        return $this->money->equals($other->money);
    }

    public function jsonSerialize(): array
    {
        return ['amount' => $this->decimalString(), 'currency' => $this->currency()];
    }
}
