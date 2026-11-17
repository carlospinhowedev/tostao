<?php

namespace App\Services;

use App\Models\Account;

/**
 * What the household has put aside: savings accounts and investment accounts,
 * each at its current value, and for investments how much of it was put in
 * against how much it is worth now.
 */
class Wealth
{
    /**
     * @return array{accounts: list<array{account: Account, value: int, put_in: ?int, gain: ?int, valued_on: ?string}>, total: int, put_in_total: int, has_gain: bool}
     */
    public function summary(): array
    {
        $accounts = Account::where('archived', false)->whereIn('type', ['savings', 'investment'])->orderBy('name')->get();

        $rows = $accounts->map(function (Account $a) {
            $value = $a->balanceCents();

            if (! $a->isInvestment()) {
                return ['account' => $a, 'value' => $value, 'put_in' => null, 'gain' => null, 'valued_on' => null];
            }

            // Deposits minus withdrawals: the money that crossed the border into the account.
            $putIn = (int) $a->transactions()->sum('amount_cents');
            $valuation = $a->latestValuation();

            return [
                'account' => $a,
                'value' => $value,
                'put_in' => $putIn,
                // A gain only means something once the account has been valued and money was put in.
                'gain' => $valuation && $putIn > 0 ? $value - $putIn : null,
                'valued_on' => $valuation?->date?->format('Y-m-d'),
            ];
        })->values();

        return [
            'accounts' => $rows->all(),
            'total' => (int) $rows->sum('value'),
            'put_in_total' => (int) $rows->sum(fn ($r) => $r['put_in'] ?? 0),
            'has_gain' => $rows->contains(fn ($r) => $r['gain'] !== null),
        ];
    }
}
