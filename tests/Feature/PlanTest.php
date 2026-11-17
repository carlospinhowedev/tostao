<?php

use App\Models\Account;
use App\Models\PlanItem;
use App\Models\Transaction;
use App\Services\PlanReport;
use App\Services\Reports;

function plan(array $attrs): PlanItem
{
    return PlanItem::create($attrs + ['kind' => 'expense', 'nature' => 'fixed', 'amount_cents' => 1000]);
}

it('shows an empty plan with a way to start', function () {
    household();

    $this->get('/plan?month=2026-11')->assertOk()->assertSee('Ainda não há nada planeado');
});

it('adds a line and keeps its colour, owner, group and category', function () {
    ['ana' => $ana] = household();
    $groceries = category('Groceries');

    $this->post('/plan', [
        'name' => 'Supermercado', 'kind' => 'expense', 'amount' => '400,00', 'owner' => 'shared',
        'nature' => 'variable', 'group_name' => ' Casa ', 'category_id' => $groceries->id, 'active' => 1,
    ])->assertRedirect();

    expect(PlanItem::first())
        ->amount_cents->toBe(40000)->owner_id->toBeNull()->nature->toBe('variable')
        ->group_name->toBe('Casa')->category_id->toBe($groceries->id)->months->toBeNull();
});

it('only keeps nature and category for expenses', function () {
    household();

    $this->post('/plan', ['name' => 'Ordenado', 'kind' => 'income', 'amount' => '1450', 'owner' => 'shared', 'nature' => 'fixed', 'category_id' => category('Groceries')->id]);

    expect(PlanItem::first())->nature->toBeNull()->category_id->toBeNull();
});

it('rejects an unknown kind, nature or owner', function () {
    household();

    $this->post('/plan', ['name' => 'x', 'kind' => 'gift', 'amount' => '1', 'owner' => 'shared'])->assertSessionHasErrors('kind');
    $this->post('/plan', ['name' => 'x', 'kind' => 'expense', 'amount' => '1', 'owner' => 'shared', 'nature' => 'huge'])->assertSessionHasErrors('nature');
    $this->post('/plan', ['name' => 'x', 'kind' => 'expense', 'amount' => '1', 'owner' => '999'])->assertSessionHasErrors('owner');
    $this->post('/plan', ['name' => 'x', 'kind' => 'expense', 'amount' => 'abc', 'owner' => 'shared'])->assertSessionHasErrors('amount');
});

it('counts a line only in the months it is listed for', function () {
    ['ana' => $ana] = household();
    plan(['name' => 'Salary', 'kind' => 'income', 'nature' => null, 'amount_cents' => 100000, 'owner_id' => $ana->id]);
    plan(['name' => 'Christmas bonus', 'kind' => 'income', 'nature' => null, 'amount_cents' => 50000, 'owner_id' => $ana->id, 'months' => [11]]);

    expect((new PlanReport(new Reports))->forMonth('2026-10')['incomeTotal'])->toBe(100000)
        ->and((new PlanReport(new Reports))->forMonth('2026-11')['incomeTotal'])->toBe(150000);
});

it('totals by person, nature and group and works out the share of income', function () {
    ['ana' => $ana, 'rui' => $rui] = household();
    plan(['name' => 'Ana pay', 'kind' => 'income', 'nature' => null, 'amount_cents' => 200000, 'owner_id' => $ana->id]);
    plan(['name' => 'Rent', 'amount_cents' => 50000, 'owner_id' => null, 'nature' => 'fixed']);
    plan(['name' => 'Electricity', 'amount_cents' => 3000, 'owner_id' => null, 'nature' => 'variable']);
    plan(['name' => 'Dog food', 'amount_cents' => 2000, 'owner_id' => null, 'nature' => 'variable', 'group_name' => 'Pet']);
    plan(['name' => 'Gym', 'amount_cents' => 4000, 'owner_id' => $rui->id, 'nature' => 'optional']);
    plan(['name' => 'Savings', 'kind' => 'saving', 'nature' => null, 'amount_cents' => 10000]);

    $r = (new PlanReport(new Reports))->forMonth('2026-11');
    $shared = collect($r['owners'])->firstWhere('key', 'shared');

    expect($r['expenseTotal'])->toBe(59000)
        ->and($r['left'])->toBe(141000)
        ->and($r['leftAfterSavings'])->toBe(131000)
        ->and($r['byNature'])->toBe(['fixed' => 50000, 'variable' => 5000, 'optional' => 4000])
        ->and($r['groups'])->toBe(['Pet' => 2000])
        ->and($shared['total'])->toBe(55000)
        ->and($shared['share'])->toBe(27.5)
        ->and(collect($r['owners'])->pluck('name')->all())->toBe([__('Shared'), 'Ana', 'Rui'])
        // Everyone carries half of the shared 550,00; Rui adds his own 40,00.
        ->and(collect($r['owners'])->firstWhere('key', (string) $rui->id)['withShared'])->toBe(27500 + 4000)
        ->and(collect($r['owners'])->firstWhere('key', (string) $ana->id)['withShared'])->toBe(27500);
});

it('compares the plan with real spending in the linked category', function () {
    ['account' => $account] = household();
    $groceries = category('Groceries');
    plan(['name' => 'Supermarket', 'amount_cents' => 40000, 'nature' => 'variable', 'category_id' => $groceries->id]);
    Transaction::create(['account_id' => $account->id, 'category_id' => $groceries->id, 'date' => '2026-11-10', 'description' => 'x', 'amount_cents' => -30000]);
    Transaction::create(['account_id' => $account->id, 'category_id' => $groceries->id, 'date' => '2026-10-31', 'description' => 'last month', 'amount_cents' => -99900]);

    $row = (new PlanReport(new Reports))->forMonth('2026-11')['vsReal'][0];

    expect($row['planned'])->toBe(40000)->and($row['actual'])->toBe(30000)->and($row['ratio'])->toBe(0.75);
    $this->get('/plan?month=2026-11')->assertOk()->assertSee('Plano contra o que aconteceu');
});

it('updates and deletes a line', function () {
    household();
    $item = plan(['name' => 'Gym', 'amount_cents' => 2500]);

    $this->put("/plan/{$item->id}", ['name' => 'Gym', 'kind' => 'expense', 'amount' => '30', 'owner' => 'shared', 'nature' => 'optional', 'active' => 0])->assertRedirect();
    expect($item->fresh())->amount_cents->toBe(3000)->active->toBeFalse();

    // An inactive line stops counting.
    expect((new PlanReport(new Reports))->forMonth('2026-11')['expenseTotal'])->toBe(0);

    $this->delete("/plan/{$item->id}")->assertRedirect();
    expect(PlanItem::count())->toBe(0);
});

it('treats every month ticked as every month', function () {
    household();

    $this->post('/plan', ['name' => 'Rent', 'kind' => 'expense', 'amount' => '500', 'owner' => 'shared', 'nature' => 'fixed', 'months' => range(1, 12)]);
    $this->post('/plan', ['name' => 'Bonus', 'kind' => 'income', 'amount' => '500', 'owner' => 'shared', 'months' => ['11', '6']]);

    expect(PlanItem::where('name', 'Rent')->first()->months)->toBeNull()
        ->and(PlanItem::where('name', 'Bonus')->first()->months)->toBe([6, 11]);
});

it('falls back to the current month for a bad month in the address', function () {
    household();

    $this->get('/plan?month=nonsense')->assertOk();
});

it('keeps a line planned at zero, so a recurring cost that is not due this month stays on the plan', function () {
    household();

    $this->post('/plan', ['name' => 'Veterinário', 'kind' => 'expense', 'amount' => '0', 'owner' => 'shared', 'nature' => 'variable', 'group_name' => 'Cão', 'category_id' => category('Health')->id])
        ->assertRedirect()->assertSessionHasNoErrors();

    $line = PlanItem::first();
    expect($line->amount_cents)->toBe(0)->and($line->active)->toBeTrue();

    // It shows on the page and, when something is spent in its category, as spending against a plan of zero.
    Transaction::create(['account_id' => Account::first()->id, 'category_id' => category('Health')->id, 'date' => '2026-11-10', 'description' => 'x', 'amount_cents' => -4500]);
    $row = (new PlanReport(new Reports))->forMonth('2026-11')['vsReal'][0];

    expect($row['planned'])->toBe(0)->and($row['actual'])->toBe(4500)->and($row['ratio'])->toBeNull();
    $this->get('/plan?month=2026-11')->assertOk()->assertSee('Veterinário');
});
