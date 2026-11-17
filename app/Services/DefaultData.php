<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;

/** What a fresh installation starts with, so the first screen is useful. */
class DefaultData
{
    public static function install(): void
    {
        if (Category::exists()) {
            return;
        }

        $categories = [
            ['Groceries', 'expense', '#2a9078'],
            ['Home and bills', 'expense', '#2f6fb5'],
            ['Mortgage and rent', 'expense', '#7a5cc2'],
            ['Insurance', 'expense', '#64748b'],
            ['Fuel and transport', 'expense', '#c98a1b'],
            ['Eating out', 'expense', '#d4553f'],
            ['Health', 'expense', '#c2548a'],
            ['Leisure', 'expense', '#0ea5a4'],
            ['Shopping', 'expense', '#a16207'],
            ['Cash withdrawals', 'expense', '#78716c'],
            ['Bank fees', 'expense', '#94a3b8'],
            ['Other', 'expense', '#9ca3af'],
            ['Salary', 'income', '#16a34a'],
            ['Other income', 'income', '#65a30d'],
            ['Transfers', 'transfer', '#a8a29e'],
        ];

        foreach ($categories as [$name, $kind, $color]) {
            Category::create(['name' => __($name), 'kind' => $kind, 'color' => $color]);
        }

        Account::create(['name' => __('Main account'), 'type' => 'bank']);
    }
}
