<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'opening_balance_cents', 'owner_id', 'archived'])]
class Account extends Model
{
    public const TYPES = ['bank', 'card', 'cash', 'savings'];

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

    public function balanceCents(): int
    {
        return $this->opening_balance_cents + (int) $this->transactions()->sum('amount_cents');
    }
}
