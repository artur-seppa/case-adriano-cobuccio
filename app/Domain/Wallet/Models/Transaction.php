<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory, HasUlids;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected static function newFactory(): Factory
    {
        return TransactionFactory::new();
    }

    protected $fillable = [
        'type', 'initiator_id', 'source_wallet_id', 'destination_wallet_id', 'amount_cents',
        'currency', 'reversal_of_transaction_id', 'reversal_reason', 'idempotency_key',
        'description', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'reversal_reason' => ReversalReason::class,
            'metadata' => 'array',
            'amount_cents' => 'integer',
        ];
    }

    protected function amount(): Attribute
    {
        return Attribute::get(fn () => Money::fromCents($this->amount_cents, $this->currency));
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reversalTransaction(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_transaction_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id');
    }

    public function isReversed(): bool
    {
        return $this->reversalTransaction()->exists();
    }
}
