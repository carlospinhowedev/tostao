<?php

use App\Models\Account;
use App\Models\BankConnection;
use App\Models\PlanItem;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringService;
use App\Services\Reports;
use App\Services\Wealth;
use Database\Seeders\DemoSeeder;
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
        ->and($data['totals'])->toMatchArray(['spent' => 10500, 'income' => 50000, 'transfers' => 0, 'change' => 39500]);
});

it('never counts transfers between accounts as spending or income', function () {
    ['ana' => $ana, 'account' => $account] = household();
    $transfer = category('Transfers');
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => -20000, 'category_id' => $transfer->id]);
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => 20000, 'category_id' => $transfer->id]);
    tx($account, ['owner_id' => $ana->id, 'amount_cents' => -300]);

    expect((new Reports)->dashboard('2026-11')['totals'])->toMatchArray(['spent' => 300, 'income' => 0, 'transfers' => 0]);
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

it('shows the month against its plan', function () {
    ['account' => $account] = household();
    tx($account, ['amount_cents' => -30000, 'date' => '2026-11-10']);
    PlanItem::create(['name' => 'Rent', 'kind' => 'expense', 'nature' => 'fixed', 'amount_cents' => 50000]);
    PlanItem::create(['name' => 'Pay', 'kind' => 'income', 'amount_cents' => 200000]);

    $this->get('/dashboard?month=2026-11')->assertOk()
        ->assertSee('Abrir o plano')
        ->assertSee('ainda previstos para este mês')
        ->assertDontSee('Fazer um plano');
});

it('offers to make a plan when there is none', function () {
    ['account' => $account] = household();
    tx($account, ['amount_cents' => -1000]);

    $this->get('/dashboard?month=2026-11')->assertOk()->assertSee('Fazer um plano');
});

it('says when spending is over the plan', function () {
    ['account' => $account] = household();
    tx($account, ['amount_cents' => -70000, 'date' => '2026-11-10']);
    PlanItem::create(['name' => 'Rent', 'kind' => 'expense', 'nature' => 'fixed', 'amount_cents' => 50000]);

    $this->get('/dashboard?month=2026-11')->assertSee('Acima do plano');
});

it('lists savings and investment accounts at their value, with what was put in and the gain', function () {
    ['account' => $account] = household();
    tx($account, ['amount_cents' => -1000]);
    $broker = Account::create(['name' => 'Corretora', 'type' => 'investment']);
    tx($broker, ['amount_cents' => 100000, 'date' => '2026-10-01']); // money put in
    $broker->recordValuation('2026-11-15', 112000);
    Account::create(['name' => 'Poupança', 'type' => 'savings', 'opening_balance_cents' => 50000]);

    $summary = app(Wealth::class)->summary();
    $row = collect($summary['accounts'])->firstWhere('account.name', 'Corretora');

    expect($summary['total'])->toBe(112000 + 50000)
        ->and($row['put_in'])->toBe(100000)
        ->and($row['gain'])->toBe(12000)
        ->and($summary['has_gain'])->toBeTrue();

    $this->get('/dashboard?month=2026-11')->assertOk()->assertSee('Poupança e investimentos')->assertSee('Corretora');
});

it('does not invent a gain for an investment account that was never valued', function () {
    household();
    $broker = Account::create(['name' => 'Corretora', 'type' => 'investment']);
    tx($broker, ['amount_cents' => 100000]);

    $row = collect(app(Wealth::class)->summary()['accounts'])->first();

    expect($row['gain'])->toBeNull()->and(app(Wealth::class)->summary()['has_gain'])->toBeFalse();
});

it('points to what needs attention: uncategorised movements, possible transfers and an expiring bank link', function () {
    ['account' => $account, 'ana' => $ana] = household();
    $personal = Account::create(['name' => 'Ana', 'type' => 'bank', 'owner_id' => $ana->id]);
    tx($account, ['amount_cents' => 30000, 'date' => '2026-11-04']);
    tx($personal, ['amount_cents' => -30000, 'date' => '2026-11-03']);
    BankConnection::create(['provider' => 'demo', 'institution_id' => 'd', 'institution_name' => 'Demo', 'state' => 's', 'status' => 'active', 'valid_until' => now()->addDays(3)]);

    $this->get('/dashboard?month=2026-11')->assertOk()
        ->assertSee('ainda não têm categoria')->assertSee('possível transferência')->assertSee('precisa de ser renovada');
});

it('shows no attention strip when there is nothing to do', function () {
    ['account' => $account] = household();
    $transfer = category('Fuel and transport');
    tx($account, ['amount_cents' => -1000, 'category_id' => $transfer->id]);

    $this->get('/dashboard?month=2026-11')->assertOk()->assertDontSee('Precisa da tua atenção');
});

it('gives the demo household a plan and a brokerage account so the whole dashboard has something to show', function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'ana@example.com')->first());

    $wealth = app(Wealth::class)->summary();

    expect(collect($wealth['accounts'])->pluck('account.name')->all())->toContain('Corretora', 'Conta poupança')
        ->and($wealth['has_gain'])->toBeTrue();
    $this->get('/dashboard')->assertOk()->assertSee('Poupança e investimentos')->assertSee('Abrir o plano');
});

it('counts what really changed: money sent to an account outside Tostão is not "kept"', function () {
    ['account' => $account] = household();
    $transfer = category('Transfers');
    tx($account, ['amount_cents' => 200000, 'date' => '2026-11-03']);                                    // salary
    tx($account, ['amount_cents' => -50000, 'date' => '2026-11-05']);                                    // spending
    tx($account, ['amount_cents' => -120000, 'date' => '2026-11-06', 'category_id' => $transfer->id]);   // sent to a personal account that is not imported

    $totals = (new Reports)->dashboard('2026-11')['totals'];

    // Income minus spending would say 1.500, but the account only gained 300.
    expect($totals)->toMatchArray(['income' => 200000, 'spent' => 50000, 'transfers' => -120000, 'change' => 30000]);
    $this->get('/dashboard?month=2026-11')->assertOk()->assertSee('Ficou')->assertSee('importa essa conta');
});

it('lets transfers between imported accounts cancel out', function () {
    ['account' => $joint, 'ana' => $ana] = household();
    $personal = Account::create(['name' => 'Ana', 'type' => 'bank', 'owner_id' => $ana->id]);
    $transfer = category('Transfers');
    tx($joint, ['amount_cents' => 100000, 'date' => '2026-11-03']);
    tx($joint, ['amount_cents' => -30000, 'date' => '2026-11-04', 'category_id' => $transfer->id]);
    tx($personal, ['amount_cents' => 30000, 'date' => '2026-11-04', 'category_id' => $transfer->id]);

    $totals = (new Reports)->dashboard('2026-11')['totals'];

    expect($totals['transfers'])->toBe(0)->and($totals['change'])->toBe(100000);
    $this->get('/dashboard?month=2026-11')->assertDontSee('importa essa conta');
});
