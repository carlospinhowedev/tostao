<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Rules\Amount;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::withCount('transactions')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return back()->with('status', __('Category created.'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('status', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete(); // transactions keep existing, just uncategorised

        return back()->with('status', __('Category deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category)],
            'kind' => ['required', 'in:'.implode(',', Category::KINDS)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'monthly_budget' => ['nullable', new Amount],
        ]);

        $budget = filled($data['monthly_budget'] ?? null) ? Money::parse($data['monthly_budget']) : null;

        return [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'color' => $data['color'],
            'monthly_budget_cents' => $data['kind'] === 'expense' && $budget ? abs($budget) : null,
        ];
    }
}
