<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RuleEngine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('lets a member add another member, but only with a valid email', function () {
    household();

    $this->post('/members', ['name' => 'Joana', 'email' => 'joana@example.com', 'password' => 'a-long-password-1', 'color' => '#c2548a'])
        ->assertSessionHasNoErrors();
    expect(User::where('email', 'joana@example.com')->exists())->toBeTrue();

    $this->post('/members', ['name' => 'Dup', 'email' => 'joana@example.com', 'password' => 'a-long-password-1', 'color' => '#c2548a'])
        ->assertSessionHasErrors('email');
});

it('refuses to remove yourself or a member who still owns transactions', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'owner_id' => $rui->id, 'date' => '2026-11-01', 'description' => 'x', 'amount_cents' => -100]);

    $this->delete("/members/{$ana->id}")->assertSessionHasErrors('member');
    $this->delete("/members/{$rui->id}")->assertSessionHasErrors('member');
    expect(User::count())->toBe(2);

    Transaction::query()->delete();
    $this->delete("/members/{$rui->id}")->assertSessionHasNoErrors();
    expect(User::count())->toBe(1);
});

it('archives rather than deletes an account that has history', function () {
    ['account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'date' => '2026-11-01', 'description' => 'x', 'amount_cents' => -100]);

    $this->delete("/accounts/{$account->id}")->assertSessionHasErrors('account');
    expect(Account::count())->toBe(1);
});

it('creates an account with an opening balance typed by a human', function () {
    household();

    $this->post('/accounts', ['name' => 'Poupança', 'type' => 'savings', 'opening_balance' => '1.500,00', 'owner' => 'shared']);

    expect(Account::where('name', 'Poupança')->first()->opening_balance_cents)->toBe(150000);
});

it('sets a monthly budget only on expense categories', function () {
    household();

    $this->post('/categories', ['name' => 'Pets', 'kind' => 'expense', 'color' => '#112233', 'monthly_budget' => '80']);
    $this->post('/categories', ['name' => 'Bonus', 'kind' => 'income', 'color' => '#112233', 'monthly_budget' => '80']);

    expect(Category::where('name', 'Pets')->value('monthly_budget_cents'))->toBe(8000)
        ->and(Category::where('name', 'Bonus')->value('monthly_budget_cents'))->toBeNull();
});

it('keeps transactions when their category is deleted', function () {
    ['account' => $account] = household();
    $cat = category('Groceries');
    Transaction::create(['account_id' => $account->id, 'category_id' => $cat->id, 'date' => '2026-11-01', 'description' => 'x', 'amount_cents' => -100]);

    $this->delete("/categories/{$cat->id}");

    expect(Transaction::count())->toBe(1)->and(Transaction::first()->category_id)->toBeNull();
});

it('applies rules by priority, case-insensitively, per field', function () {
    ['ana' => $ana] = household();
    Rule::create(['pattern' => 'galp', 'category_id' => category('Fuel and transport')->id, 'priority' => 10]);
    Rule::create(['pattern' => 'galp', 'category_id' => category('Other')->id, 'priority' => 20]);
    Rule::create(['pattern' => 'GALP CENTRO', 'set_owner' => true, 'owner_id' => $ana->id, 'priority' => 30]);

    $suggestion = (new RuleEngine)->suggest('Compra Galp Centro');

    expect($suggestion['category_id'])->toBe(category('Fuel and transport')->id)
        ->and($suggestion['owner_id'])->toBe($ana->id)
        ->and($suggestion['set_owner'])->toBeTrue();
    expect((new RuleEngine)->suggest('Padaria')['category_id'])->toBeNull();
});

it('switches language and formats money for it', function () {
    household();

    $this->post('/locale', ['locale' => 'en'])->assertSessionHas('locale', 'en');
    $this->get('/dashboard')->assertSee('Nothing to show yet');

    $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
});

it('prunes abandoned uploads but keeps recent ones', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $disk->put('imports/old.csv', 'x');
    $disk->put('imports/new.csv', 'x');
    touch($disk->path('imports/old.csv'), now()->subDays(2)->getTimestamp());

    $this->artisan('tostao:prune-imports')->assertSuccessful();

    $disk->assertMissing('imports/old.csv');
    $disk->assertExists('imports/new.csv');
});

it('runs the recurring command from the console', function () {
    ['account' => $account] = household(login: false);
    RecurringTransaction::create([
        'description' => 'Rent', 'amount_cents' => -1000, 'account_id' => $account->id,
        'frequency' => 'monthly', 'next_date' => now()->subDay()->format('Y-m-d'),
    ]);

    $this->artisan('tostao:recurring')->expectsOutputToContain('Created 1 recurring')->assertSuccessful();
});

it('rejects an owner that is neither shared nor a member', function () {
    ['account' => $account] = household();

    $this->post('/transactions', [
        'date' => '2026-11-10', 'description' => 'x', 'amount' => '1', 'type' => 'expense',
        'account_id' => $account->id, 'owner' => '999',
    ])->assertSessionHasErrors('owner');

    $this->post('/accounts', ['name' => 'X', 'type' => 'bank', 'owner' => '999'])->assertSessionHasErrors('owner');
    $this->post('/rules', ['pattern' => 'x', 'owner' => '999'])->assertSessionHasErrors('owner');
    expect(Transaction::count())->toBe(0);
});

it('validates the per-row choices when committing an import', function () {
    ['account' => $account] = household();
    Storage::fake('local');
    Storage::disk('local')->put('imports/'.($token = (string) Str::uuid()).'.csv', 'd;t;v
01/01/2026;x;1,00
');

    $this->post("/import/{$token}", [
        'account_id' => $account->id, 'delimiter' => ';', 'has_header' => 1, 'date_format' => 'd/m/Y', 'decimal' => 'auto',
        'date' => 0, 'description' => 1, 'amount' => 2, 'rows' => [2], 'category' => [2 => '999'], 'owner' => [2 => '999'],
    ])->assertSessionHasErrors(['category.2', 'owner.2']);
});

it('accepts a zero opening balance and rejects text', function () {
    household();

    $this->post('/accounts', ['name' => 'Zero', 'type' => 'cash', 'opening_balance' => '0', 'owner' => 'shared'])->assertSessionHasNoErrors();
    $this->post('/accounts', ['name' => 'Bad', 'type' => 'cash', 'opening_balance' => 'abc', 'owner' => 'shared'])->assertSessionHasErrors('opening_balance');
});
