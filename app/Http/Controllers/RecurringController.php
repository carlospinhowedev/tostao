<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\OwnerChoice;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringController extends Controller
{
    public function index(): View
    {
        return view('recurring.index', [
            'items' => RecurringTransaction::with(['account', 'category', 'owner'])->orderByDesc('active')->orderBy('next_date')->get(),
            'accounts' => Account::where('archived', false)->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'members' => User::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RecurringTransaction::create($this->validated($request) + ['active' => true]);

        return back()->with('status', __('Recurring transaction created.'));
    }

    public function update(Request $request, RecurringTransaction $recurring): RedirectResponse
    {
        $recurring->update($this->validated($request) + ['active' => $request->boolean('active')]);

        return back()->with('status', __('Recurring transaction updated.'));
    }

    public function destroy(RecurringTransaction $recurring): RedirectResponse
    {
        $recurring->delete();

        return back()->with('status', __('Recurring transaction deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', new Amount],
            'type' => ['required', 'in:expense,income'],
            'account_id' => ['required', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'owner' => ['required', new OwnerChoice],
            'paid_by_id' => ['nullable', 'exists:users,id'],
            'frequency' => ['required', 'in:'.implode(',', RecurringTransaction::FREQUENCIES)],
            'next_date' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:next_date'],
        ]);

        $cents = abs(Money::parse($data['amount']));
        $ownerId = $data['owner'] === 'shared' ? null : (int) $data['owner'];

        return [
            'description' => $data['description'],
            'amount_cents' => $data['type'] === 'expense' ? -$cents : $cents,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'owner_id' => $ownerId,
            'paid_by_id' => $ownerId === null && $data['type'] === 'expense' ? ($data['paid_by_id'] ?? null) : null,
            'frequency' => $data['frequency'],
            'next_date' => $data['next_date'],
            'ends_on' => $data['ends_on'] ?? null,
        ];
    }
}
