<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Wallet\Enums\WalletType;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['type', 'user_id', 'reference', 'currency', 'balance_cents', 'entry_count'];

    protected static function newFactory(): Factory
    {
        return WalletFactory::new();
    }

    protected function casts(): array
    {
        return ['type' => WalletType::class, 'balance_cents' => 'integer', 'entry_count' => 'integer'];
    }

    protected function balance(): Attribute
    {
        return Attribute::get(fn () => Money::fromCents($this->balance_cents, $this->currency));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
