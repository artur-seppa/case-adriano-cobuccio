<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\ValueObjects\Money;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory, HasUlids;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected static function newFactory(): Factory
    {
        return LedgerEntryFactory::new();
    }

    protected $fillable = [
        'transaction_id', 'wallet_id', 'direction', 'amount_cents', 'currency',
        'balance_after_cents', 'sequence',
    ];

    protected function casts(): array
    {
        return [
            'direction' => EntryDirection::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'sequence' => 'integer',
        ];
    }

    protected function amount(): Attribute
    {
        return Attribute::get(fn () => Money::fromCents($this->amount_cents, $this->currency));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
