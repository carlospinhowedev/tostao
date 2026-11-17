<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Rules\Amount;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Typing in what an investment account is worth today, for brokers with no API (XTB, say). */
class ValuationController extends Controller
{
    public function store(Request $request, Account $account): RedirectResponse
    {
        abort_unless($account->isInvestment(), 404);

        $data = $request->validate([
            'value' => ['required', new Amount(allowZero: true)],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $account->recordValuation($data['date'] ?? now()->format('Y-m-d'), Money::parse($data['value']));

        return back()->with('status', __('Value saved.'));
    }
}
