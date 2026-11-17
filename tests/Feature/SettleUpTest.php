<?php

use App\Models\Settlement;
use App\Models\Transaction;
use App\Services\SettleUp;

function sharedExpense($account, $payer, int $cents): void
{
    Transaction::create([
        'account_id' => $account->id, 'owner_id' => null, 'paid_by_id' => $payer?->id,
        'date' => '2026-11-10', 'description' => 'Shared', 'amount_cents' => -$cents,
    ]);
}

it('splits shared expenses equally between the members', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    sharedExpense($account, $ana, 10000);
    sharedExpense($account, $rui, 4000);

    $result = (new SettleUp)->calculate();

    expect($result['balances'])->toBe([$ana->id => 3000, $rui->id => -3000])
        ->and($result['payments'])->toBe([['from' => $rui->id, 'to' => $ana->id, 'amount_cents' => 3000]]);
});

it('ignores expenses paid from the joint account', function () {
    ['account' => $account] = household();
    sharedExpense($account, null, 50000);

    expect((new SettleUp)->calculate()['payments'])->toBe([]);
});

it('ignores personal expenses', function () {
    ['ana' => $ana, 'account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'owner_id' => $ana->id, 'paid_by_id' => $ana->id, 'date' => '2026-11-10', 'description' => 'Mine', 'amount_cents' => -9999]);

    expect((new SettleUp)->calculate()['payments'])->toBe([]);
});

it('handles an odd number of cents without losing any', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    sharedExpense($account, $ana, 1001);

    $balances = (new SettleUp)->calculate()['balances'];

    expect($balances[$ana->id])->toBe(500)->and($balances[$rui->id])->toBe(-500)
        ->and(array_sum($balances))->toBe(0);
});

it('lets members record a payment that clears the debt', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    sharedExpense($account, $ana, 10000);

    $this->post('/settle-up', [
        'from_user_id' => $rui->id, 'to_user_id' => $ana->id, 'amount' => '50,00', 'date' => '2026-11-12',
    ])->assertSessionHasNoErrors();

    expect(Settlement::first()->amount_cents)->toBe(5000)
        ->and((new SettleUp)->calculate()['payments'])->toBe([]);
    $this->get('/settle-up')->assertSee('Estão quites');
});

it('rejects a payment from someone to themselves', function () {
    ['ana' => $ana] = household();

    $this->post('/settle-up', ['from_user_id' => $ana->id, 'to_user_id' => $ana->id, 'amount' => '5', 'date' => '2026-11-12'])
        ->assertSessionHasErrors('from_user_id');
});

it('shows who owes whom on the page', function () {
    ['ana' => $ana, 'account' => $account] = household();
    sharedExpense($account, $ana, 10000);

    $this->get('/settle-up')->assertOk()->assertSee('Rui')->assertSee('deve a')->assertSee('50,00');
});
