<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard shows. Aggregation happens in PHP over one query so
 * it behaves the same on SQLite, MySQL and PostgreSQL.
 *
 * Transfers (category kind "transfer") never count as spending or income.
 */
class Reports
{
    public const SHARED = 'shared';

    /** @return list<string> available months, newest first */
    public function months(): array
    {
        return Transaction::query()->pluck('date')
            ->map(fn ($d) => $d->format('Y-m'))->unique()->sortDesc()->values()->all();
    }

    /**
     * @param  ?string  $month  "YYYY-MM" or null for everything
     * @return array<string, mixed>
     */
    public function dashboard(?string $month): array
    {
        $members = User::orderBy('id')->get();
        $categories = Category::all()->keyBy('id');

        $every = Transaction::query()->inMonth($month)->get();
        $tx = $every->reject(fn ($t) => $this->isTransfer($t, $categories));

        // Money that only moved between accounts. Counted nowhere as spending or income, but
        // it did leave or enter the accounts in Tostão, so it is part of what really changed.
        $transfers = (int) $every->filter(fn ($t) => $this->isTransfer($t, $categories))->sum('amount_cents');

        $keys = $members->pluck('id')->map(fn ($id) => (string) $id)->push(self::SHARED)->all();
        $people = [];
        foreach ($keys as $key) {
            $mine = $tx->filter(fn ($t) => $this->ownerKey($t) === $key);
            $people[$key] = [
                'spent' => -$mine->where('amount_cents', '<', 0)->sum('amount_cents'),
                'income' => $mine->where('amount_cents', '>', 0)->sum('amount_cents'),
                'count' => $mine->count(),
            ];
            $people[$key]['net'] = $people[$key]['income'] - $people[$key]['spent'];
        }

        return [
            'month' => $month,
            'members' => $members,
            'people' => $people,
            'totals' => [
                'spent' => array_sum(array_column($people, 'spent')),
                'income' => array_sum(array_column($people, 'income')),
                // Net transfers: negative means more left for accounts outside Tostão than came back.
                'transfers' => $transfers,
                // What really happened to the balance of the accounts in Tostão over the period.
                'change' => array_sum(array_column($people, 'income')) - array_sum(array_column($people, 'spent')) + $transfers,
            ],
            'byCategory' => $this->byCategory($tx, $categories, $keys),
            'trend' => $this->trend($month, $categories, $keys),
            'budgets' => $this->budgets($month ?? now()->format('Y-m'), $categories),
            'topMerchants' => $this->topMerchants($tx),
            'accounts' => Account::where('archived', false)->orderBy('name')->get()
                ->map(fn ($a) => ['account' => $a, 'balance' => $a->balanceCents()]),
        ];
    }

    private function isTransfer(Transaction $t, Collection $categories): bool
    {
        return $t->category_id && ($categories[$t->category_id]->kind ?? null) === 'transfer';
    }

    private function ownerKey(Transaction $t): string
    {
        return $t->owner_id ? (string) $t->owner_id : self::SHARED;
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{name: ?string, color: string, total: int, by: array<string, int>}>
     */
    private function byCategory(Collection $tx, Collection $categories, array $keys): array
    {
        $rows = [];
        foreach ($tx->where('amount_cents', '<', 0) as $t) {
            $id = $t->category_id ?? 0;
            $rows[$id] ??= [
                'name' => $id ? $categories[$id]->name : null,
                'color' => $id ? $categories[$id]->color : '#94a3b8',
                'total' => 0,
                'by' => array_fill_keys($keys, 0),
            ];
            $rows[$id]['total'] += -$t->amount_cents;
            $rows[$id]['by'][$this->ownerKey($t)] += -$t->amount_cents;
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /** @return list<array{month: string, by: array<string, int>, total: int}> six months ending at $month */
    private function trend(?string $month, Collection $categories, array $keys): array
    {
        $end = Carbon::createFromFormat('!Y-m', $month ?? now()->format('Y-m'));
        $start = $end->copy()->subMonths(5);

        $tx = Transaction::betweenDates($start->format('Y-m-01'), $end->format('Y-m-t'))
            ->where('amount_cents', '<', 0)->get()
            ->reject(fn ($t) => $this->isTransfer($t, $categories))
            ->groupBy(fn ($t) => $t->date->format('Y-m'));

        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i)->format('Y-m');
            $by = array_fill_keys($keys, 0);
            foreach ($tx->get($m, []) as $t) {
                $by[$this->ownerKey($t)] += -$t->amount_cents;
            }
            $out[] = ['month' => $m, 'by' => $by, 'total' => array_sum($by)];
        }

        return $out;
    }

    /** @return list<array{category: Category, budget: int, spent: int, ratio: float}> */
    private function budgets(string $month, Collection $categories): array
    {
        $withBudget = $categories->filter(fn ($c) => $c->monthly_budget_cents > 0 && $c->kind === 'expense');
        if ($withBudget->isEmpty()) {
            return [];
        }

        $spent = Transaction::inMonth($month)->where('amount_cents', '<', 0)
            ->whereIn('category_id', $withBudget->keys())->get()
            ->groupBy('category_id')->map(fn ($g) => -$g->sum('amount_cents'));

        return $withBudget->map(fn ($c) => [
            'category' => $c,
            'budget' => $c->monthly_budget_cents,
            'spent' => (int) ($spent[$c->id] ?? 0),
            'ratio' => ($spent[$c->id] ?? 0) / $c->monthly_budget_cents,
        ])->sortByDesc('ratio')->values()->all();
    }

    /** @return list<array{name: string, total: int, count: int}> */
    private function topMerchants(Collection $tx): array
    {
        return $tx->where('amount_cents', '<', 0)
            ->groupBy(fn ($t) => mb_strtolower($t->description))
            ->map(fn ($g) => [
                'name' => $g->first()->description,
                'total' => -$g->sum('amount_cents'),
                'count' => $g->count(),
            ])
            ->sortByDesc('total')->take(6)->values()->all();
    }
}
