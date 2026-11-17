<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\OwnerChoice;
use App\Services\RuleEngine;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuleController extends Controller
{
    public function index(Request $request): View
    {
        // "Make a rule from this transaction" links here with the transaction's details.
        $from = $request->filled('from') ? Transaction::find((int) $request->query('from')) : null;

        return view('rules.index', [
            'prefill' => $from ? [
                'pattern' => Rule::suggestKeyword($from->description),
                'category_id' => $from->category_id,
                'owner' => $from->owner_id ?? '',
                'description' => $from->description,
            ] : null,
            'rules' => Rule::with(['category', 'owner'])->orderBy('priority')->orderBy('id')->get(),
            'categories' => Category::orderBy('name')->get(),
            'members' => User::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Rule::create($this->validated($request));

        return back()->with('status', __('Rule created.'));
    }

    public function update(Request $request, Rule $rule): RedirectResponse
    {
        $rule->update($this->validated($request));

        return back()->with('status', __('Rule updated.'));
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Rule deleted.'));
    }

    /**
     * Run the rules over transactions that have no category yet, so a new rule
     * can tidy what was imported before it existed. Anything already
     * categorised (by a rule or by hand) is left alone.
     */
    public function apply(RuleEngine $engine): RedirectResponse
    {
        $changed = 0;

        Transaction::whereNull('category_id')->orderBy('id')->each(function (Transaction $t) use ($engine, &$changed) {
            $s = $engine->suggest($t->description, $t->amount_cents);
            if ($s['category_id'] === null && ! $s['set_owner']) {
                return;
            }

            $t->update([
                'category_id' => $s['category_id'],
                'owner_id' => $s['set_owner'] ? $s['owner_id'] : $t->owner_id,
                'paid_by_id' => $s['set_owner'] && $s['owner_id'] !== null ? null : $t->paid_by_id,
            ]);
            $changed++;
        });

        return back()->with('status', trans_choice(':count transaction updated.|:count transactions updated.', $changed, ['count' => $changed]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:100'],
            'exclude_pattern' => ['nullable', 'string', 'max:100'],
            'amount_multiple' => ['nullable', new Amount],
            'category_id' => ['nullable', 'exists:categories,id'],
            'owner' => ['nullable', new OwnerChoice(allowEmpty: true)], // "", "shared" or a user id
            'priority' => ['nullable', 'integer', 'between:1,9999'],
        ]);

        $owner = $data['owner'] ?? '';

        return [
            'pattern' => trim($data['pattern']),
            'exclude_pattern' => filled($data['exclude_pattern'] ?? null) ? trim($data['exclude_pattern']) : null,
            'amount_multiple_cents' => filled($data['amount_multiple'] ?? null) ? abs(Money::parse($data['amount_multiple'])) : null,
            'category_id' => $data['category_id'] ?? null,
            'set_owner' => $owner !== '',
            'owner_id' => in_array($owner, ['', 'shared'], true) ? null : (int) $owner,
            'priority' => $data['priority'] ?? 100,
        ];
    }
}
