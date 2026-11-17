<?php

namespace App\Http\Controllers;

use App\Brokers\BrokerRegistry;
use App\Models\Account;
use App\Models\BrokerLink;
use App\Services\BrokerSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrokerController extends Controller
{
    public function __construct(private BrokerRegistry $brokers) {}

    public function index(): View
    {
        return view('brokers.index', [
            'providers' => $this->brokers->available(),
            'links' => BrokerLink::with('account')->get()->keyBy('provider'),
            'accounts' => Account::where('archived', false)->where('type', 'investment')->orderBy('name')->get(),
            'investments' => Account::where('archived', false)->where('type', 'investment')->orderBy('name')->get(),
        ]);
    }

    public function link(Request $request, string $provider, BrokerSync $sync): RedirectResponse
    {
        abort_unless(isset($this->brokers->available()[$provider]), 404);

        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'sync_from' => ['nullable', 'date'],
        ]);

        $account = Account::findOrFail($data['account_id']);
        if (! $account->isInvestment()) {
            return back()->withErrors(['account_id' => __('Choose an account of the type Investments.')]);
        }

        $link = BrokerLink::updateOrCreate(
            ['provider' => $provider],
            ['account_id' => $account->id, 'sync_from' => $data['sync_from'] ?? null, 'last_synced_at' => null, 'last_error' => null],
        );

        return $this->report($link, $sync->sync($link->fresh()));
    }

    public function sync(string $provider, BrokerSync $sync): RedirectResponse
    {
        $link = BrokerLink::where('provider', $provider)->firstOrFail();

        return $this->report($link, $sync->sync($link));
    }

    public function destroy(string $provider): RedirectResponse
    {
        BrokerLink::where('provider', $provider)->delete();

        return back()->with('status', __('Link removed. Everything already imported was kept.'));
    }

    private function report(BrokerLink $link, int $created): RedirectResponse
    {
        $link->refresh();

        if ($link->last_error) {
            return back()->withErrors(['broker' => $link->last_error]);
        }

        return back()->with('status', trans_choice(':count new transaction imported.|:count new transactions imported.', $created, ['count' => $created]));
    }
}
