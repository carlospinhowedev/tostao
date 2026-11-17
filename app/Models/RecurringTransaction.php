<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['description', 'amount_cents', 'account_id', 'category_id', 'owner_id', 'paid_by_id', 'frequency', 'next_date', 'day_of_month', 'ends_on', 'active'])]
class RecurringTransaction extends Model
{
    public const FREQUENCIES = ['weekly', 'monthly', 'yearly'];

    protected function casts(): array
    {
        return ['next_date' => 'date', 'ends_on' => 'date', 'active' => 'boolean', 'amount_cents' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            // Remember the intended day; every later date is computed from it.
            if ($item->isDirty('next_date') || $item->day_of_month === null) {
                $item->day_of_month = Carbon::parse($item->next_date)->day;
            }
        });
    }

    public function advance(Carbon $from): Carbon
    {
        if ($this->frequency === 'weekly') {
            return $from->copy()->addWeek();
        }

        $next = $from->copy()->startOfMonth()->{$this->frequency === 'yearly' ? 'addYear' : 'addMonth'}();

        return $next->day(min($this->day_of_month ?? $from->day, $next->daysInMonth));
    }
}
