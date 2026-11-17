<?php

namespace App\Services;

use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;

/**
 * Who owes whom. A shared expense that one member paid out of their own
 * pocket (owner = shared, paid_by = member) is split equally between all
 * members. Expenses paid from a joint account create no debt.
 */
class SettleUp
{
    /**
     * Balances: positive means the household owes that member money, negative
     * means the member owes the others.
     *
     * @return array{balances: array<int, int>, payments: list<array{from: int, to: int, amount_cents: int}>}
     */
    public function calculate(): array
    {
        $members = User::orderBy('id')->pluck('id')->all();
        $balances = array_fill_keys($members, 0);

        if (count($members) < 2) {
            return ['balances' => $balances, 'payments' => []];
        }

        $shared = Transaction::whereNull('owner_id')->whereNotNull('paid_by_id')
            ->where('amount_cents', '<', 0)->get(['amount_cents', 'paid_by_id']);

        foreach ($shared as $t) {
            if (! isset($balances[$t->paid_by_id])) {
                continue;
            }
            $total = -$t->amount_cents;
            $balances[$t->paid_by_id] += $total;
            foreach ($this->split($total, $members, $t->paid_by_id) as $id => $share) {
                $balances[$id] -= $share;
            }
        }

        foreach (Settlement::all() as $s) {
            if (isset($balances[$s->from_user_id], $balances[$s->to_user_id])) {
                $balances[$s->from_user_id] += $s->amount_cents;
                $balances[$s->to_user_id] -= $s->amount_cents;
            }
        }

        return ['balances' => $balances, 'payments' => $this->minimise($balances)];
    }

    /**
     * Equal split in whole cents. The leftover cents go to the payer first so the
     * parts always add up to the total.
     *
     * @param  list<int>  $members
     * @return array<int, int>
     */
    public function split(int $total, array $members, int $payer): array
    {
        $base = intdiv($total, count($members));
        $rest = $total - $base * count($members);
        $shares = array_fill_keys($members, $base);

        $order = array_merge([$payer], array_diff($members, [$payer]));
        foreach ($order as $id) {
            if ($rest <= 0) {
                break;
            }
            $shares[$id]++;
            $rest--;
        }

        return $shares;
    }

    /**
     * Fewest payments that clear every balance (greedy: biggest debtor pays
     * biggest creditor).
     *
     * @param  array<int, int>  $balances
     * @return list<array{from: int, to: int, amount_cents: int}>
     */
    public function minimise(array $balances): array
    {
        $debtors = array_filter($balances, fn ($b) => $b < 0);
        $creditors = array_filter($balances, fn ($b) => $b > 0);
        asort($debtors);
        arsort($creditors);

        $payments = [];
        while ($debtors && $creditors) {
            $d = array_key_first($debtors);
            $c = array_key_first($creditors);
            $amount = min(-$debtors[$d], $creditors[$c]);
            $payments[] = ['from' => $d, 'to' => $c, 'amount_cents' => $amount];

            $debtors[$d] += $amount;
            $creditors[$c] -= $amount;
            if ($debtors[$d] === 0) {
                unset($debtors[$d]);
            }
            if ($creditors[$c] === 0) {
                unset($creditors[$c]);
            }
            asort($debtors);
            arsort($creditors);
        }

        return $payments;
    }
}
