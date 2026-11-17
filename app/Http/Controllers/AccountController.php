<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\OwnerChoice;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('accounts.index', [
            'accounts' => Account::with('owner')->orderBy('archived')->orderBy('name')->get(),
            'members' => User::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Account::create($this->validated($request));

        return back()->with('status', __('Account created.'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($this->validated($request) + ['archived' => $request->boolean('archived')]);

        return back()->with('status', __('Account updated.'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->transactions()->exists()) {
            return back()->withErrors(['account' => __('This account has transactions. Archive it instead of deleting it.')]);
        }

        $account->delete();

        return back()->with('status', __('Account deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:'.implode(',', Account::TYPES)],
            'opening_balance' => ['nullable', new Amount(allowZero: true)],
            'owner' => ['required', new OwnerChoice],
        ]);

        $opening = filled($data['opening_balance'] ?? null) ? Money::parse($data['opening_balance']) : 0;

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'opening_balance_cents' => $opening,
            'owner_id' => $data['owner'] === 'shared' ? null : (int) $data['owner'],
        ];
    }
}
