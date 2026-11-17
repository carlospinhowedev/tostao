<?php

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

function payload(array $overrides = []): array
{
    return array_merge([
        'date' => '2026-11-10',
        'description' => 'Pingo Doce',
        'amount' => '12,50',
        'type' => 'expense',
        'account_id' => DB::table('accounts')->value('id'),
        'category_id' => null,
        'owner' => 'shared',
    ], $overrides);
}

it('stores an expense typed the Portuguese way as negative cents', function () {
    household();

    $this->post('/transactions', payload(['amount' => '1.234,56']))->assertRedirect('/transactions');

    expect(Transaction::first()->amount_cents)->toBe(-123456);
});

it('stores income as positive cents', function () {
    household();

    $this->post('/transactions', payload(['type' => 'income', 'amount' => '1186.35']));

    expect(Transaction::first()->amount_cents)->toBe(118635);
});

it('rejects an amount that is not a number', function () {
    household();

    $this->post('/transactions', payload(['amount' => 'abc']))->assertSessionHasErrors('amount');
    expect(Transaction::count())->toBe(0);
});

it('remembers who paid a shared expense but ignores it for personal ones', function () {
    ['ana' => $ana, 'rui' => $rui] = household();

    $this->post('/transactions', payload(['paid_by_id' => $rui->id]));
    $this->post('/transactions', payload(['owner' => (string) $ana->id, 'paid_by_id' => $rui->id, 'description' => 'Personal']));

    expect(Transaction::where('description', 'Pingo Doce')->first()->paid_by_id)->toBe($rui->id)
        ->and(Transaction::where('description', 'Personal')->first()->paid_by_id)->toBeNull();
});

it('updates and deletes a transaction', function () {
    household();
    $this->post('/transactions', payload());
    $t = Transaction::first();

    $this->put("/transactions/{$t->id}", payload(['amount' => '20,00', 'description' => 'Changed']))->assertRedirect('/transactions');
    expect($t->fresh()->amount_cents)->toBe(-2000)->and($t->fresh()->description)->toBe('Changed');

    $this->delete("/transactions/{$t->id}");
    expect(Transaction::count())->toBe(0);
});

it('filters the list by month, person and text', function () {
    ['ana' => $ana, 'account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'owner_id' => $ana->id, 'date' => '2026-10-05', 'description' => 'Farmácia', 'amount_cents' => -500]);
    Transaction::create(['account_id' => $account->id, 'owner_id' => null, 'date' => '2026-11-05', 'description' => 'Mercado', 'amount_cents' => -700]);

    $this->get('/transactions?month=2026-10')->assertSee('Farmácia')->assertDontSee('Mercado');
    $this->get('/transactions?owner=shared')->assertSee('Mercado')->assertDontSee('Farmácia');
    $this->get('/transactions?owner='.$ana->id)->assertSee('Farmácia')->assertDontSee('Mercado');
    $this->get('/transactions?q=merc')->assertSee('Mercado')->assertDontSee('Farmácia');
});

it('treats % in the search as a literal character', function () {
    ['account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'date' => '2026-11-05', 'description' => 'Mercado', 'amount_cents' => -700]);

    $this->get('/transactions?q=%25')->assertDontSee('Mercado');
});

it('exports the filtered list as a CSV Excel can open', function () {
    ['account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'date' => '2026-11-05', 'description' => 'Café', 'amount_cents' => -150]);

    $response = $this->get('/transactions/export');
    $csv = $response->streamedContent();

    $response->assertOk()->assertDownload();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('2026-11-05;Café;-1,50');
});
