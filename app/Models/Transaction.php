<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'category_id', 'owner_id', 'paid_by_id', 'recurring_transaction_id', 'date', 'description', 'amount_cents', 'notes', 'import_hash'])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'amount_cents' => 'integer'];
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

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }

    public function isExpense(): bool
    {
        return $this->amount_cents < 0;
    }

    /** Filter by month as "YYYY-MM". */
    public function scopeInMonth(Builder $query, ?string $month): Builder
    {
        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $query;
        }

        $start = $month.'-01';

        return $query->betweenDates($start, date('Y-m-t', strtotime($start)));
    }

    /**
     * Inclusive date range. Compares the date part only, because SQLite keeps
     * dates written through Eloquent as "2026-11-30 00:00:00", which a plain
     * BETWEEN '…-01' AND '…-30' would silently drop for the last day.
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }

    /** "shared" filters joint transactions, a number filters a member. */
    public function scopeForOwner(Builder $query, ?string $owner): Builder
    {
        return match (true) {
            $owner === null || $owner === '' => $query,
            $owner === 'shared' => $query->whereNull('owner_id'),
            default => $query->where('owner_id', (int) $owner),
        };
    }
}
