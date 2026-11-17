<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'institution_id', 'institution_name', 'state', 'session_id', 'status', 'valid_until', 'last_synced_at', 'last_error'])]
class BankConnection extends Model
{
    protected function casts(): array
    {
        return [
            'session_id' => 'encrypted',
            'valid_until' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /** Whole days left before the bank asks for a new consent (null = unknown). */
    public function daysLeft(): ?int
    {
        return $this->valid_until ? (int) max(0, now()->startOfDay()->diffInDays($this->valid_until->copy()->startOfDay(), false)) : null;
    }

    /** True when the consent runs out within two weeks. */
    public function expiresSoon(): bool
    {
        $days = $this->daysLeft();

        return $this->status === 'active' && $days !== null && $days <= 14;
    }
}
