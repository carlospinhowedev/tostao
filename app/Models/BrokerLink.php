<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider', 'account_id', 'sync_from', 'last_synced_at', 'last_error'])]
class BrokerLink extends Model
{
    protected function casts(): array
    {
        return ['sync_from' => 'date', 'last_synced_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
