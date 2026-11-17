<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'opening_balance_cents', 'owner_id', 'archived'])]
class Account extends Model
{
    public const TYPES = ['bank', 'card', 'cash', 'savings', 'investment'];

    /** English labels, translated where they are shown. */
    public const LABELS = ['bank' => 'Bank account', 'card' => 'Card', 'cash' => 'Cash', 'savings' => 'Savings', 'investment' => 'Investments'];

    public static function label(string $type): string
    {
        return __(self::LABELS[$type] ?? 'Bank account');
    }

    public function isInvestment(): bool
    {
        return $this->type === 'investment';
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(AccountValuation::class);
    }

    /**
     * One value per day: a second one on the same day replaces the first. Matched
     * with whereDate because SQLite keeps Eloquent dates as "2026-11-17 00:00:00".
     */
    public function recordValuation(string $date, int $valueCents, string $source = 'manual'): AccountValuation
    {
        $existing = $this->valuations()->whereDate('date', $date)->first();

        if ($existing) {
            $existing->update(['value_cents' => $valueCents, 'source' => $source]);

            return $existing;
        }

        return $this->valuations()->create(['date' => $date, 'value_cents' => $valueCents, 'source' => $source]);
    }

    public function latestValuation(): ?AccountValuation
    {
        return $this->valuations()->orderByDesc('date')->first();
    }

    protected function casts(): array
    {
        return ['archived' => 'boolean', 'opening_balance_cents' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * What the account holds. For an investment account that is its latest
     * valuation (the transactions are only deposits and withdrawals, which say
     * nothing about gains); for everything else, opening balance plus movements.
     */
    public function balanceCents(): int
    {
        if ($this->isInvestment() && ($valuation = $this->latestValuation())) {
            return $valuation->value_cents;
        }

        return $this->opening_balance_cents + (int) $this->transactions()->sum('amount_cents');
    }
}
