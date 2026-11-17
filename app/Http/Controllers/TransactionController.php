<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\OwnerChoice;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        $sum = (clone $query)->sum('amount_cents');
        $transactions = $query->with(['account', 'category', 'owner', 'paidBy'])
            ->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'uncategorised' => Transaction::whereNull('category_id')->count(),
            'sum' => (int) $sum,
            'months' => Transaction::pluck('date')->map(fn ($d) => $d->format('Y-m'))->unique()->sortDesc()->values(),
            'members' => User::orderBy('id')->get(),
            'categories' => Category::orderBy('name')->get(),
            'accounts' => Account::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('transactions.form', $this->formData(new Transaction([
            'date' => now(), 'account_id' => Account::where('archived', false)->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        Transaction::create($this->validated($request));

        return redirect()->route('transactions.index')->with('status', __('Transaction saved.'));
    }

    public function edit(Transaction $transaction): View
    {
        return view('transactions.form', $this->formData($transaction));
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $transaction->update($this->validated($request));

        return redirect()->route('transactions.index')->with('status', __('Transaction updated.'));
    }

    /** Change the category and/or person straight from the table, without opening the form. */
    public function quick(Request $request, Transaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'owner' => ['required', new OwnerChoice],
        ]);

        $ownerId = $data['owner'] === 'shared' ? null : (int) $data['owner'];

        $transaction->update([
            'category_id' => $data['category_id'] ?? null,
            'owner_id' => $ownerId,
            // "Paid by" only means something for shared expenses.
            'paid_by_id' => $ownerId === null ? $transaction->paid_by_id : null,
        ]);

        return response()->json(['ok' => true]);
    }

    /** Set the category and/or person of every ticked row. A blank choice leaves that field alone. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:transactions,id'],
            'category' => ['nullable', 'string'],
            'owner' => ['nullable', new OwnerChoice(allowEmpty: true)],
        ], ['ids.required' => __('Tick at least one transaction first.')]);

        $category = $data['category'] ?? '';
        if ($category !== '' && $category !== 'none') {
            $request->validate(['category' => ['exists:categories,id']]);
        }

        $changes = [];
        if ($category === 'none') {
            $changes['category_id'] = null;
        } elseif ($category !== '') {
            $changes['category_id'] = (int) $category;
        }

        $owner = $data['owner'] ?? '';
        if ($owner !== '') {
            $changes['owner_id'] = $owner === 'shared' ? null : (int) $owner;
            if ($owner !== 'shared') {
                $changes['paid_by_id'] = null;
            }
        }

        if ($changes === []) {
            return back()->withErrors(['bulk' => __('Choose a category or a person to apply.')]);
        }

        $count = Transaction::whereIn('id', $data['ids'])->update($changes);

        return back()->with('status', trans_choice(':count transaction updated.|:count transactions updated.', $count, ['count' => $count]));
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('transactions.index')->with('status', __('Transaction deleted.'));
    }

    /** Download the filtered list as CSV (UTF-8 with BOM so Excel opens it correctly). */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with(['account', 'category', 'owner'])->orderBy('date')->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Date'), __('Description'), __('Amount'), __('Category'), __('Who'), __('Account')], ';', '"', '');
            $query->each(function (Transaction $t) use ($out) {
                fputcsv($out, [
                    $t->date->format('Y-m-d'),
                    $t->description,
                    Money::format($t->amount_cents, false),
                    $t->category?->name,
                    $t->owner?->name ?? __('Shared'),
                    $t->account->name,
                ], ';', '"', '');
            });
            fclose($out);
        }, 'tostao-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        return Transaction::query()
            ->inMonth($request->query('month'))
            ->forOwner($request->query('owner'))
            ->when($request->query('category'), fn ($q, $c) => $c === 'none' ? $q->whereNull('category_id') : $q->where('category_id', (int) $c))
            ->when($request->query('account'), fn ($q, $a) => $q->where('account_id', (int) $a))
            ->when($request->query('type'), fn ($q, $t) => $t === 'expense' ? $q->where('amount_cents', '<', 0) : $q->where('amount_cents', '>', 0))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where('description', 'like', '%'.addcslashes($s, '%_\\').'%'));
    }

    /** @return array<string, mixed> */
    private function formData(Transaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'members' => User::orderBy('id')->get(),
            'categories' => Category::orderBy('name')->get(),
            'accounts' => Account::where('archived', false)->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', new Amount],
            'type' => ['required', 'in:expense,income'],
            'account_id' => ['required', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'owner' => ['required', new OwnerChoice],
            'paid_by_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $cents = abs(Money::parse($data['amount']));
        $ownerId = $data['owner'] === 'shared' ? null : (int) $data['owner'];

        return [
            'date' => $data['date'],
            'description' => $data['description'],
            'amount_cents' => $data['type'] === 'expense' ? -$cents : $cents,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'owner_id' => $ownerId,
            // "Paid by" only means something for shared expenses.
            'paid_by_id' => $ownerId === null && $data['type'] === 'expense' ? ($data['paid_by_id'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
