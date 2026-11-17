<?php

namespace App\Http\Controllers;

use App\Banking\BankException;
use App\Banking\BankRegistry;
use App\Models\Account;
use App\Models\BankConnection;
use App\Models\Transaction;
use App\Services\BankSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BankController extends Controller
{
    public function __construct(private BankRegistry $banks) {}

    public function index(Request $request): View
    {
        $providers = $this->banks->available();
        $provider = $providers[$request->query('provider')] ?? ($providers ? reset($providers) : null);
        $country = strtoupper((string) $request->query('country', config('tostao.banks.country')));
        $institutions = [];
        $error = null;

        // Only list banks when someone is about to pick one: it is a call to the provider.
        if ($provider && $request->boolean('browse')) {
            try {
                $institutions = $provider->institutions($country);
            } catch (BankException $e) {
                $error = $e->getMessage();
            }
        }

        return view('banks.index', [
            'enabled' => $providers !== [],
            'providers' => $providers,
            'provider' => $provider,
            'country' => $country,
            'institutions' => $institutions,
            'browse' => $request->boolean('browse'),
            'error' => $error,
            'connections' => BankConnection::with('accounts.account')->orderByDesc('id')->get(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'institution' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(isset($this->banks->available()[$data['provider']]), 404);
        $provider = $this->banks->get($data['provider']);

        $connection = BankConnection::create([
            'provider' => $provider->key(),
            'institution_id' => $data['institution'],
            'institution_name' => $data['institution_name'],
            'state' => Str::random(40),
            'status' => 'pending',
        ]);

        try {
            $start = $provider->startConsent(
                $data['institution'],
                $connection->state,
                $this->redirectUrl(),
                now()->addDays((int) config('tostao.banks.consent_days')),
            );
        } catch (BankException $e) {
            $connection->delete();

            return redirect()->route('banks.index')->withErrors(['bank' => $e->getMessage()]);
        }

        return redirect()->away($start->url);
    }

    /** Where the bank sends the person back to, with a code and our state. */
    public function callback(Request $request): RedirectResponse
    {
        $connection = BankConnection::where('state', (string) $request->query('state'))
            ->where('status', 'pending')
            ->first();

        if (! $connection) {
            return redirect()->route('banks.index')->withErrors(['bank' => __('That bank connection link is no longer valid. Start again.')]);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            $connection->delete();

            return redirect()->route('banks.index')->withErrors(['bank' => __('The bank did not authorise the connection.')]);
        }

        try {
            $consent = $this->banks->get($connection->provider)->completeConsent((string) $request->query('code'));
        } catch (BankException $e) {
            $connection->delete();

            return redirect()->route('banks.index')->withErrors(['bank' => $e->getMessage()]);
        }

        $connection->update([
            'session_id' => $consent->sessionId,
            'status' => 'active',
            'valid_until' => $consent->validUntil ?? now()->addDays((int) config('tostao.banks.consent_days')),
            'last_error' => null,
        ]);

        foreach ($consent->accounts as $remote) {
            $connection->accounts()->updateOrCreate(
                ['remote_id' => $remote->id],
                ['name' => $remote->name, 'iban' => $remote->iban],
            );
        }

        $this->inheritFromPrevious($connection);

        return redirect()->route('banks.show', $connection)->with('status', __('Connected. Choose which account each bank account goes into.'));
    }

    /**
     * Renewing a consent creates a new connection to the same bank. Carry the
     * account mapping over (matched by id, or IBAN when the bank changes ids)
     * and retire the old one, so nothing has to be set up twice.
     */
    private function inheritFromPrevious(BankConnection $connection): void
    {
        $previous = BankConnection::with('accounts')
            ->where('provider', $connection->provider)
            ->where('institution_id', $connection->institution_id)
            ->where('id', '!=', $connection->id)
            ->where('status', '!=', 'pending')
            ->get();

        foreach ($previous as $old) {
            foreach ($connection->accounts as $new) {
                $match = $old->accounts->first(fn ($o) => $o->remote_id === $new->remote_id)
                    ?? ($new->iban ? $old->accounts->first(fn ($o) => $o->iban === $new->iban) : null);

                if ($match && $match->account_id) {
                    $new->update([
                        'account_id' => $match->account_id,
                        'sync_from' => $match->sync_from,
                        'last_synced_at' => $match->last_synced_at,
                    ]);
                }
            }

            $old->delete();
        }
    }

    public function show(BankConnection $connection): View
    {
        return view('banks.show', [
            'connection' => $connection->load('accounts.account'),
            'accounts' => Account::where('archived', false)->orderBy('name')->get(),
            'defaultFrom' => now()->subDays((int) config('tostao.banks.history_days'))->format('Y-m-d'),
        ]);
    }

    public function link(Request $request, BankConnection $connection, BankSync $sync): RedirectResponse
    {
        $data = $request->validate([
            'links' => ['required', 'array'],
            'links.*.account' => ['nullable', 'string'],
            'links.*.name' => ['nullable', 'string', 'max:100'],
            'links.*.from' => ['nullable', 'date'],
        ]);

        foreach ($connection->accounts as $bank) {
            $choice = $data['links'][$bank->id] ?? null;
            $target = $choice['account'] ?? '';

            if ($target === '' || $target === 'skip') {
                $bank->update(['account_id' => null]);

                continue;
            }

            if ($target === 'new') {
                $account = Account::create(['name' => ($choice['name'] ?? null) ?: $bank->name, 'type' => 'bank', 'opening_balance_cents' => 0]);
            } else {
                $account = Account::findOrFail((int) $target);
            }

            // Start after what the account already holds, so a first sync does not
            // re-import months that were loaded from a CSV file.
            $from = $choice['from'] ?? null;
            if (! $from) {
                $last = Transaction::where('account_id', $account->id)->max('date');
                $from = $last ? substr((string) $last, 0, 10) : now()->subDays((int) config('tostao.banks.history_days'))->format('Y-m-d');
            }

            $bank->update(['account_id' => $account->id, 'sync_from' => $from, 'last_synced_at' => null]);
        }

        $created = $sync->sync($connection->fresh());

        return redirect()->route('banks.index')->with('status', trans_choice(':count new transaction imported.|:count new transactions imported.', $created, ['count' => $created]));
    }

    public function sync(BankConnection $connection, BankSync $sync): RedirectResponse
    {
        $created = $sync->sync($connection);
        $connection->refresh();

        if ($connection->last_error) {
            return back()->withErrors(['bank' => $connection->last_error]);
        }

        return back()->with('status', trans_choice(':count new transaction imported.|:count new transactions imported.', $created, ['count' => $created]));
    }

    public function destroy(BankConnection $connection): RedirectResponse
    {
        // Imported transactions stay: they are the household's records now.
        $connection->delete();

        return redirect()->route('banks.index')->with('status', __('Bank connection removed. The transactions already imported were kept.'));
    }

    private function redirectUrl(): string
    {
        return config('tostao.banks.redirect_url') ?: route('banks.callback');
    }
}
