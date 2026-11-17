<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PlanItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The month on paper: planned income, planned spending by person and group,
 * what is left, and how real spending compares through each item's category.
 */
class PlanReport
{
    public function __construct(private Reports $reports) {}

    /**
     * @param  string  $month  "YYYY-MM"
     * @return array<string, mixed>
     */
    public function forMonth(string $month): array
    {
        $number = (int) substr($month, 5, 2);

        /** @var Collection<int, PlanItem> $items */
        $items = PlanItem::with(['owner', 'category'])->where('active', true)->orderBy('name')->get()
            ->filter(fn (PlanItem $i) => $i->appliesTo($number))->values();

        $income = $items->where('kind', 'income');
        $expenses = $items->where('kind', 'expense');
        $savings = $items->where('kind', 'saving');

        $incomeTotal = (int) $income->sum('amount_cents');
        $expenseTotal = (int) $expenses->sum('amount_cents');
        $savingTotal = (int) $savings->sum('amount_cents');

        $owners = [];
        foreach (User::orderBy('id')->get()->pluck('name', 'id')->prepend(__('Shared'), Reports::SHARED) as $key => $name) {
            $mine = $expenses->filter(fn (PlanItem $i) => ($i->owner_id === null ? Reports::SHARED : (string) $i->owner_id) === (string) $key);
            // A member with nothing of their own still carries their half of what is shared.
            if ($mine->isEmpty() && ((string) $key === Reports::SHARED || $expenses->isEmpty())) {
                continue;
            }

            $total = (int) $mine->sum('amount_cents');
            $owners[] = [
                'key' => (string) $key,
                'name' => $name,
                'total' => $total,
                'share' => $incomeTotal > 0 ? round($total / $incomeTotal * 100, 1) : null,
                'byNature' => collect(PlanItem::NATURES)->mapWithKeys(fn ($n) => [$n => (int) $mine->where('nature', $n)->sum('amount_cents')])->all(),
                'groups' => $mine->groupBy(fn (PlanItem $i) => $i->group_name ?? '')
                    ->map(fn (Collection $g) => ['items' => $g->values(), 'total' => (int) $g->sum('amount_cents')])
                    ->sortBy(fn ($g, $name) => $name === '' ? '' : mb_strtolower($name))->all(),
            ];
        }

        // Each person's own spending plus an equal part of what is shared.
        $members = max(1, User::count());
        $sharedTotal = (int) (collect($owners)->firstWhere('key', Reports::SHARED)['total'] ?? 0);
        foreach ($owners as &$o) {
            if ($o['key'] !== Reports::SHARED) {
                $o['withShared'] = $o['total'] + intdiv($sharedTotal, $members);
                $o['withSharedShare'] = $incomeTotal > 0 ? round($o['withShared'] / $incomeTotal * 100, 1) : null;
            }
        }
        unset($o);

        $real = $this->reports->dashboard($month)['totals'];

        return [
            'month' => $month,
            'income' => $income->values(),
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'savings' => $savings->values(),
            'savingTotal' => $savingTotal,
            'left' => $incomeTotal - $expenseTotal,
            'leftAfterSavings' => $incomeTotal - $expenseTotal - $savingTotal,
            'owners' => $owners,
            'byNature' => collect(PlanItem::NATURES)->mapWithKeys(fn ($n) => [$n => (int) $expenses->where('nature', $n)->sum('amount_cents')])->all(),
            'groups' => $expenses->filter(fn ($i) => $i->group_name)->groupBy('group_name')
                ->map(fn (Collection $g) => (int) $g->sum('amount_cents'))->sortKeys()->all(),
            'vsReal' => $this->versusReal($month, $expenses),
            'real' => ['spent' => $real['spent'], 'income' => $real['income'], 'left' => $real['income'] - $real['spent']],
        ];
    }

    /**
     * Planned against actual spending, one row per linked category.
     *
     * @param  Collection<int, PlanItem>  $expenses
     * @return list<array{category: Category, planned: int, actual: int, ratio: ?float}>
     */
    private function versusReal(string $month, Collection $expenses): array
    {
        $linked = $expenses->whereNotNull('category_id')->groupBy('category_id');
        if ($linked->isEmpty()) {
            return [];
        }

        $actual = Transaction::query()->inMonth($month)->whereIn('category_id', $linked->keys())
            ->where('amount_cents', '<', 0)->get()->groupBy('category_id');

        return $linked->map(function (Collection $group, $categoryId) use ($actual) {
            $planned = (int) $group->sum('amount_cents');
            $spent = -(int) ($actual->get($categoryId)?->sum('amount_cents') ?? 0);

            return [
                'category' => $group->first()->category,
                'planned' => $planned,
                'actual' => $spent,
                'ratio' => $planned > 0 ? round($spent / $planned, 2) : null,
            ];
        })->sortByDesc('actual')->values()->all();
    }
}
