<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Rule;
use App\Models\User;
use App\Rules\OwnerChoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuleController extends Controller
{
    public function index(): View
    {
        return view('rules.index', [
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

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'owner' => ['nullable', new OwnerChoice(allowEmpty: true)], // "", "shared" or a user id
            'priority' => ['nullable', 'integer', 'between:1,9999'],
        ]);

        $owner = $data['owner'] ?? '';

        return [
            'pattern' => trim($data['pattern']),
            'category_id' => $data['category_id'] ?? null,
            'set_owner' => $owner !== '',
            'owner_id' => in_array($owner, ['', 'shared'], true) ? null : (int) $owner,
            'priority' => $data['priority'] ?? 100,
        ];
    }
}
