<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PlanItem;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\OwnerChoice;
use App\Services\PlanReport;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request, PlanReport $report): View
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $date = Carbon::createFromFormat('!Y-m', $month);

        return view('plan.index', [
            'plan' => $report->forMonth($month),
            'month' => $month,
            'previous' => $date->copy()->subMonth()->format('Y-m'),
            'next' => $date->copy()->addMonth()->format('Y-m'),
            'label' => $date->locale(app()->getLocale())->translatedFormat('F Y'),
            'items' => PlanItem::with(['owner', 'category'])->orderBy('kind')->orderBy('name')->get(),
            'members' => User::orderBy('id')->get(),
            'categories' => Category::where('kind', 'expense')->orderBy('name')->get(),
            'groups' => PlanItem::whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PlanItem::create($this->validated($request));

        return back()->with('status', __('Plan line added.'));
    }

    public function update(Request $request, PlanItem $item): RedirectResponse
    {
        $item->update($this->validated($request));

        return back()->with('status', __('Plan line updated.'));
    }

    public function destroy(PlanItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', __('Plan line deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(PlanItem::KINDS)],
            'amount' => ['required', new Amount(allowZero: true)],
            'owner' => ['required', new OwnerChoice],
            'nature' => ['nullable', Rule::in(PlanItem::NATURES)],
            'group_name' => ['nullable', 'string', 'max:60'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'months' => ['nullable', 'array'],
            'months.*' => ['integer', 'between:1,12'],
        ]);

        $isExpense = $data['kind'] === 'expense';
        $months = collect($data['months'] ?? [])->map(fn ($m) => (int) $m)->unique()->sort()->values()->all();

        return [
            'name' => trim($data['name']),
            'kind' => $data['kind'],
            'amount_cents' => abs(Money::parse($data['amount'])),
            'owner_id' => $data['owner'] === 'shared' ? null : (int) $data['owner'],
            'nature' => $isExpense ? ($data['nature'] ?? 'fixed') : null,
            'group_name' => filled($data['group_name'] ?? null) ? trim($data['group_name']) : null,
            'category_id' => $isExpense ? ($data['category_id'] ?? null) : null,
            // Every month ticked is the same as none ticked.
            'months' => count($months) === 12 || $months === [] ? null : $months,
            'active' => $request->boolean('active', true),
        ];
    }
}
