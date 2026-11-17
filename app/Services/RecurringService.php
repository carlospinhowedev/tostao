<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class RecurringService
{
    /**
     * Create every transaction that fell due up to $today. Safe to call as
     * often as you like: each recurring item advances its own next_date, so a
     * missed week or month is caught up exactly once.
     */
    public function generateDue(?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $created = 0;

        RecurringTransaction::where('active', true)->whereDate('next_date', '<=', $today)->each(function ($item) use ($today, &$created) {
            $date = $item->next_date->copy();

            while ($date->lte($today) && (! $item->ends_on || $date->lte($item->ends_on))) {
                Transaction::create([
                    'account_id' => $item->account_id,
                    'category_id' => $item->category_id,
                    'owner_id' => $item->owner_id,
                    'paid_by_id' => $item->paid_by_id,
                    'recurring_transaction_id' => $item->id,
                    'date' => $date->format('Y-m-d'),
                    'description' => $item->description,
                    'amount_cents' => $item->amount_cents,
                ]);
                $created++;
                $date = $item->advance($date);
            }

            $item->update([
                'next_date' => $date,
                'active' => ! $item->ends_on || $date->lte($item->ends_on),
            ]);
        });

        return $created;
    }
}
