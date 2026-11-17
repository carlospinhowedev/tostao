<?php

use App\Models\Rule;
use App\Models\Transaction;
use App\Services\ImportService;
use App\Services\RuleEngine;

function charge($account, int $cents, string $description = 'COMPRA REPSOL', array $extra = []): Transaction
{
    return Transaction::create($extra + [
        'account_id' => $account->id, 'date' => '2026-11-10', 'description' => $description, 'amount_cents' => $cents,
    ]);
}

it('changes category and person from the table without opening the form', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    $t = charge($account, -2000, extra: ['owner_id' => null, 'paid_by_id' => $rui->id]);
    $fuel = category('Fuel and transport');

    $this->patchJson("/transactions/{$t->id}/quick", ['category_id' => $fuel->id, 'owner' => (string) $ana->id])
        ->assertOk()->assertJson(['ok' => true]);

    expect($t->fresh())->category_id->toBe($fuel->id)->owner_id->toBe($ana->id)->paid_by_id->toBeNull();
});

it('rejects an unknown person or category in the quick edit', function () {
    ['account' => $account] = household();
    $t = charge($account, -2000);

    $this->patchJson("/transactions/{$t->id}/quick", ['category_id' => 9999, 'owner' => 'shared'])->assertStatus(422);
    $this->patchJson("/transactions/{$t->id}/quick", ['category_id' => null, 'owner' => '9999'])->assertStatus(422);
});

it('changes several transactions at once and leaves blank fields alone', function () {
    ['ana' => $ana, 'rui' => $rui, 'account' => $account] = household();
    $fuel = category('Fuel and transport');
    $a = charge($account, -1000, extra: ['owner_id' => $rui->id, 'category_id' => $fuel->id]);
    $b = charge($account, -2000, extra: ['owner_id' => $rui->id, 'category_id' => $fuel->id]);
    $untouched = charge($account, -3000, extra: ['owner_id' => $rui->id]);

    $this->post('/transactions/bulk', ['ids' => [$a->id, $b->id], 'category' => '', 'owner' => (string) $ana->id])
        ->assertRedirect()->assertSessionHas('status');

    expect($a->fresh()->owner_id)->toBe($ana->id)
        ->and($a->fresh()->category_id)->toBe($fuel->id) // blank category = unchanged
        ->and($untouched->fresh()->owner_id)->toBe($rui->id);

    $this->post('/transactions/bulk', ['ids' => [$a->id], 'category' => 'none', 'owner' => ''])->assertRedirect();
    expect($a->fresh()->category_id)->toBeNull()->and($a->fresh()->owner_id)->toBe($ana->id);
});

it('asks for something to apply and for something selected', function () {
    ['account' => $account] = household();
    $t = charge($account, -1000);

    $this->post('/transactions/bulk', ['ids' => [$t->id], 'category' => '', 'owner' => ''])->assertSessionHasErrors('bulk');
    $this->post('/transactions/bulk', ['category' => 'none'])->assertSessionHasErrors('ids');
});

it('matches a keyword only for round amounts when the rule says so', function () {
    ['ana' => $ana] = household();
    $tobacco = category('Other');
    $fuel = category('Fuel and transport');
    Rule::create(['pattern' => 'REPSOL', 'amount_multiple_cents' => 500, 'category_id' => $tobacco->id, 'owner_id' => $ana->id, 'set_owner' => true, 'priority' => 10]);
    Rule::create(['pattern' => 'REPSOL', 'category_id' => $fuel->id, 'priority' => 20]);
    $engine = new RuleEngine;

    expect($engine->suggest('COMPRA REPSOL LISBOA', -2000))->toMatchArray(['category_id' => $tobacco->id, 'owner_id' => $ana->id, 'set_owner' => true])
        ->and($engine->suggest('COMPRA REPSOL LISBOA', -4000)['category_id'])->toBe($tobacco->id)
        ->and($engine->suggest('COMPRA REPSOL LISBOA', -1526))->toMatchArray(['category_id' => $fuel->id, 'set_owner' => false])
        ->and($engine->suggest('COMPRA REPSOL LISBOA'))->toMatchArray(['category_id' => $fuel->id]); // no amount known: the multiple cannot be met
});

it('saves the multiple from the rules form', function () {
    household();

    $this->post('/rules', ['pattern' => 'GALP', 'amount_multiple' => '5,00', 'priority' => 10])->assertRedirect();

    expect(Rule::first()->amount_multiple_cents)->toBe(500);
    $this->get('/rules')->assertOk()->assertSee('múltiplos');

    $this->post('/rules', ['pattern' => 'BP', 'amount_multiple' => 'abc'])->assertSessionHasErrors('amount_multiple');
});

it('applies rules to uncategorised transactions only', function () {
    ['ana' => $ana, 'account' => $account] = household();
    $tobacco = category('Other');
    $fuel = category('Fuel and transport');
    Rule::create(['pattern' => 'REPSOL', 'amount_multiple_cents' => 500, 'category_id' => $tobacco->id, 'owner_id' => $ana->id, 'set_owner' => true, 'priority' => 10]);
    Rule::create(['pattern' => 'REPSOL', 'category_id' => $fuel->id, 'priority' => 20]);

    $round = charge($account, -2000);
    $odd = charge($account, -1526);
    $manual = charge($account, -4000, extra: ['category_id' => $fuel->id]); // already categorised by hand

    $this->post('/rules/apply')->assertRedirect()->assertSessionHas('status');

    expect($round->fresh())->category_id->toBe($tobacco->id)->owner_id->toBe($ana->id)
        ->and($odd->fresh())->category_id->toBe($fuel->id)->owner_id->toBeNull()
        ->and($manual->fresh()->category_id)->toBe($fuel->id)->and($manual->fresh()->owner_id)->toBeNull();
});

it('applies the amount condition during import suggestions', function () {
    ['ana' => $ana, 'account' => $account] = household();
    $tobacco = category('Other');
    Rule::create(['pattern' => 'REPSOL', 'amount_multiple_cents' => 500, 'category_id' => $tobacco->id, 'owner_id' => $ana->id, 'set_owner' => true]);

    $rows = [
        ['line' => 1, 'date' => '2026-11-10', 'description' => 'COMPRA REPSOL', 'amount_cents' => -2000],
        ['line' => 2, 'date' => '2026-11-10', 'description' => 'COMPRA REPSOL', 'amount_cents' => -1526],
    ];
    $prepared = app(ImportService::class)->prepare($account, $rows);

    expect($prepared[0]['category_id'])->toBe($tobacco->id)->and($prepared[1]['category_id'])->toBeNull();
});

it('skips a rule when the description has the excluded word', function () {
    household();
    $fuel = category('Fuel and transport');
    $cash = category('Cash withdrawals');
    Rule::create(['pattern' => 'LEV ATM', 'category_id' => $cash->id, 'priority' => 5]);
    Rule::create(['pattern' => 'REPSOL', 'exclude_pattern' => 'LEV ATM', 'category_id' => $fuel->id, 'priority' => 10]);
    $engine = new RuleEngine;

    expect($engine->suggest('COMPRA EST SERVICO REPSOL', -1526)['category_id'])->toBe($fuel->id)
        ->and($engine->suggest('LEV ATM 1234 BTA REPSOL', -2000)['category_id'])->toBe($cash->id);
});

it('saves the excluded word from the rules form', function () {
    household();

    $this->post('/rules', ['pattern' => 'REPSOL', 'exclude_pattern' => ' LEV ATM '])->assertRedirect();

    expect(Rule::first()->exclude_pattern)->toBe('LEV ATM');
});
