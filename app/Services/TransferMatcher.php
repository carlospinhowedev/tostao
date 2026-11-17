<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Finds money that only moved between the household's own accounts: a
 * withdrawal in one account and a deposit of the same amount in another a few
 * days apart. Those are not spending or income, but a bank statement cannot
 * tell you that, so the pair is proposed and a person confirms it.
 *
 * Only unambiguous pairs are proposed: if two accounts could each have received
 * the money, guessing would be worse than asking the person to categorise it.
 */
class TransferMatcher
{
    /** How many days apart the two halves of a transfer may be booked. */
    public const WINDOW_DAYS = 3;

    /** @return list<array{out: Transaction, in: Transaction}> */
    public function proposals(): array
    {
        $transferIds = Category::where('kind', 'transfer')->pluck('id');

        /** @var Collection<int, Transaction> $open */
        $open = Transaction::with(['account', 'category'])
            ->where(fn ($q) => $q->whereNull('category_id')->orWhereNotIn('category_id', $transferIds))
            ->orderBy('date')->orderBy('id')->get();

        $outs = $open->where('amount_cents', '<', 0);
        $ins = $open->where('amount_cents', '>', 0)->groupBy('amount_cents');

        $candidates = fn (Transaction $a, Collection $pool) => $pool->filter(
            fn (Transaction $b) => $b->account_id !== $a->account_id
                && abs($a->date->diffInDays($b->date)) <= self::WINDOW_DAYS
        )->values();

        $pairs = [];
        $used = [];

        foreach ($outs as $out) {
            $pool = $ins->get(-$out->amount_cents, collect());
            $matches = $candidates($out, $pool);
            if ($matches->count() !== 1) {
                continue;
            }

            $in = $matches->first();
            // The deposit must also have this withdrawal as its only candidate.
            $back = $candidates($in, $outs->where('amount_cents', -$in->amount_cents));
            if ($back->count() !== 1 || isset($used[$in->id])) {
                continue;
            }

            $used[$in->id] = true;
            $pairs[] = ['out' => $out, 'in' => $in];
        }

        return $pairs;
    }

    /**
     * Mark both halves as transfers.
     *
     * @param  list<array{0: int, 1: int}>  $pairs  [withdrawal id, deposit id]
     * @return int transactions updated
     */
    public function confirm(array $pairs): int
    {
        $category = Category::where('kind', 'transfer')->first();
        if (! $category) {
            return 0;
        }

        $ids = collect($pairs)->flatten()->map(fn ($i) => (int) $i)->unique()->all();

        return Transaction::whereIn('id', $ids)->update(['category_id' => $category->id]);
    }
}
