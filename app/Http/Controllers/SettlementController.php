<?php

namespace App\Http\Controllers;

use App\Models\Settlement;
use App\Models\User;
use App\Rules\Amount;
use App\Services\SettleUp;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function index(SettleUp $settleUp): View
    {
        return view('settle.index', [
            'result' => $settleUp->calculate(),
            'members' => User::orderBy('id')->get()->keyBy('id'),
            'history' => Settlement::with(['from', 'to'])->orderByDesc('date')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_user_id' => ['required', 'exists:users,id', 'different:to_user_id'],
            'to_user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', new Amount],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        Settlement::create([
            'from_user_id' => $data['from_user_id'],
            'to_user_id' => $data['to_user_id'],
            'amount_cents' => abs(Money::parse($data['amount'])),
            'date' => $data['date'],
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('Payment recorded.'));
    }

    public function destroy(Settlement $settlement): RedirectResponse
    {
        $settlement->delete();

        return back()->with('status', __('Payment removed.'));
    }
}
