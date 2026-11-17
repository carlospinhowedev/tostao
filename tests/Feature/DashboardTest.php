<?php

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringService;
use App\Services\Reports;
use Illuminate\Support\Carbon;

function tx($account, array $attrs): Transaction
{
    return Transaction::create($attrs + ['account_id' => $account->id, 'date' => '2026-11-10', 'description' => 'x']);
}

it('shows an empty state before any transaction exists', function () {
    household();

    $this->get('/dashboard')->assertOk()->assertSee('Ainda não há nada para mostrar');
});

it('adds up spending and income per person and per household', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => -1000]);
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => 50000]);
    tx($account, ['owner_id' => $rui->id, 'amount_cents' => -2500]);
    tx($account, ['owner_id' => null, 'amount_cents' => -7000]);

    $data = (new Reports)->dashboard('2026-11');

    expect($data['people'][(string) $ana->id])->toMatchArray(['spent' => 1000, 'income' => 50000, 'net' => 49000])
        ->and($data['people'][(string) $rui->id]['spent'])->toBe(2500)
        ->and($data['people']['shared']['spent'])->toBe(7000)
        ->and($data['totals'])->toBe(['spent' => 10500, 'income' => 50000]);
});

it('never counts transfers between accounts as spending or income', function () {
    ['ana' => $ana, 'account' => $account] = household();
    $transfer = category('Transfers');
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => -20000, 'category_id' => $transfer->id]);
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => 20000, 'category_id' => $transfer->id]);
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => -300]);

    expect((new Reports)->dashboard('2026-11')['totals'])->toBe(['spent' => 300, 'income' => 0]);
});

it('limits a month to that month only', function () {
    ['account' => $account] = household();
    tx($account, ['date' => '2026-10-31', 'amount_cents' => -100]);
    tx($account, ['date' => '2026-11-01', 'amount_cents' => -200]);
    tx($account, ['date' => '2026-11-30', 'amount_cents' => -400]);
    tx($account, ['date' => '2026-12-01', 'amount_cents' => -800]);

    expect((new Reports)->dashboard('2026-11')['totals']['spent'])->toBe(600)
        ->and((new Reports)->dashboard(null)['totals']['spent'])->toBe(1500);
});

it('tracks budgets and flags the ones that are over', function () {
    ['account' => $account] = household();
    $groceries = category('Groceries');
    $groceries->update(['monthly_budget_cents' => 10000]);
    tx($account, ['category_id' => $groceries->id, 'amount_cents' => -12500]);

    $budget = (new Reports)->dashboard('2026-11')['budgets'][0];

    expect($budget['spent'])->toBe(12500)->and($budget['budget'])->toBe(10000)->and($budget['ratio'])->toBe(1.25);
    $this->get('/dashboard?month=2026-11')->assertSee('Acima do orçamento');
});

it('reports account balances from the opening balance plus transactions', function () {
    ['account' => $account] = household();
    $account->update(['opening_balance_cents' => 100000]);
    tx($account, ['amount_cents' => -2500]);
    tx($account, ['amount_cents' => 1000]);

    expect($account->fresh()->balanceCents())->toBe(98500);
});

it('creates recurring transactions that fell due, catching up missed months once', function () {
    ['account' => $account] = household();
    $item = RecurringTransaction::create([
        'description' => 'Rent', 'amount_cents' => -50000, 'account_id' => $account->id,
        'frequency' => 'monthly', 'next_date' => '2026-08-31',
    ]);

    $created = (new RecurringService)->generateDue(Carbon::parse('2026-11-15'));

    expect($created)->toBe(3) // Aug 31, Sep 30 (short month), Oct 31 (back on the 31st)
        ->and(Transaction::orderBy('date')->pluck('date')->map->format('Y-m-d')->all())->toBe(['2026-08-31', '2026-09-30', '2026-10-31'])
        ->and($item->fresh()->next_date->format('Y-m-d'))->toBe('2026-11-30');

    // Running it again the same day creates nothing.
    expect((new RecurringService)->generateDue(Carbon::parse('2026-11-15')))->toBe(0);
});

it('stops a recurring item after its end date', function () {
    ['account' => $account] = household();
    $item = RecurringTransaction::create([
        'description' => 'Gym', 'amount_cents' => -3000, 'account_id' => $account->id,
        'frequency' => 'monthly', 'next_date' => '2026-09-10', 'ends_on' => '2026-10-10',
    ]);

    (new RecurringService)->generateDue(Carbon::parse('2026-12-01'));

    expect(Transaction::count())->toBe(2)->and($item->fresh()->active)->toBeFalse();
});

it('creates due recurring transactions when someone opens the dashboard', function () {
    ['account' => $account] = household();
    RecurringTransaction::create([
        'description' => 'Netflix', 'amount_cents' => -1299, 'account_id' => $account->id,
        'frequency' => 'weekly', 'next_date' => now()->subDays(8)->format('Y-m-d'),
    ]);

    $this->get('/dashboard')->assertOk();

    expect(Transaction::count())->toBe(2);
});

it('keeps a monthly item on its day instead of drifting after a short month', function () {
    ['account' => $account] = household();
    $item = RecurringTransaction::create([
        'description' => 'Rent', 'amount_cents' => -50000, 'account_id' => $account->id,
        'frequency' => 'monthly', 'next_date' => '2027-01-31',
    ]);

    (new RecurringService)->generateDue(Carbon::parse('2027-04-30'));

    expect(Transaction::orderBy('date')->pluck('date')->map->format('Y-m-d')->all())
        ->toBe(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'])
        ->and($item->fresh()->next_date->format('Y-m-d'))->toBe('2027-05-31');
});
