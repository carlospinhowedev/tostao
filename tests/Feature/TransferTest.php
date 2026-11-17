<?php

use App\Models\Account;
use App\Models\Rule;
use App\Models\Transaction;
use App\Services\Backup;
use App\Services\Reports;
use App\Services\TransferMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function move(Account $account, int $cents, string $date, string $description = 'TRF'): Transaction
{
    return Transaction::create(['account_id' => $account->id, 'date' => $date, 'description' => $description, 'amount_cents' => $cents]);
}

it('proposes a withdrawal and a deposit of the same amount in different accounts', function () {
    ['account' => $joint, 'ana' => $ana] = household();
    $personal = Account::create(['name' => 'Ana', 'type' => 'bank', 'owner_id' => $ana->id]);
    $out = move($personal, -30000, '2026-11-03');
    $in = move($joint, 30000, '2026-11-04');
    move($joint, -1234, '2026-11-04'); // unrelated

    $pairs = (new TransferMatcher)->proposals();

    expect($pairs)->toHaveCount(1)
        ->and($pairs[0]['out']->id)->toBe($out->id)
        ->and($pairs[0]['in']->id)->toBe($in->id);
});

it('leaves out pairs that are too far apart, in the same account or ambiguous', function () {
    ['account' => $joint, 'ana' => $ana, 'rui' => $rui] = household();
    $a = Account::create(['name' => 'Ana', 'type' => 'bank', 'owner_id' => $ana->id]);
    $r = Account::create(['name' => 'Rui', 'type' => 'bank', 'owner_id' => $rui->id]);

    move($a, -10000, '2026-11-01');
    move($joint, 10000, '2026-11-20');   // 19 days later: not the same movement

    move($joint, -5000, '2026-11-05');
    move($joint, 5000, '2026-11-05');    // same account

    move($a, -7000, '2026-11-08');
    move($joint, 7000, '2026-11-08');    // two accounts could have received it
    move($r, 7000, '2026-11-09');

    expect((new TransferMatcher)->proposals())->toBe([]);
});

it('does not propose what is already a transfer', function () {
    ['account' => $joint, 'ana' => $ana] = household();
    $a = Account::create(['name' => 'Ana', 'type' => 'bank', 'owner_id' => $ana->id]);
    $transfer = category('Transfers');
    move($a, -100, '2026-11-03')->update(['category_id' => $transfer->id]);
    move($joint, 100, '2026-11-03')->update(['category_id' => $transfer->id]);

    expect((new TransferMatcher)->proposals())->toBe([]);
});

it('confirms ticked pairs as transfers and ignores pairs that were not proposed', function () {
    ['account' => $joint, 'ana' => $ana] = household();
    $a = Account::create(['name' => 'Ana', 'type' => 'bank', 'owner_id' => $ana->id]);
    $out = move($a, -30000, '2026-11-03');
    $in = move($joint, 30000, '2026-11-03');
    $other = move($joint, -999, '2026-11-03');

    $this->get('/transfers')->assertOk()->assertSee('300,00');
    $this->post('/transfers', ['pairs' => ["{$out->id}:{$in->id}", "{$other->id}:{$in->id}"]])->assertRedirect('/transfers');

    $transfer = category('Transfers');
    expect($out->fresh()->category_id)->toBe($transfer->id)
        ->and($in->fresh()->category_id)->toBe($transfer->id)
        ->and($other->fresh()->category_id)->toBeNull();

    // And they no longer count as spending or income.
    expect((new Reports)->dashboard('2026-11')['totals'])->toBe(['spent' => 999, 'income' => 0]);
});

it('asks for something to be ticked', function () {
    household();

    $this->post('/transfers', [])->assertSessionHasErrors('pairs');
});

it('builds a starting keyword for a rule from a bank description', function () {
    expect(Rule::suggestKeyword('COMPRA 1234 EST SERVICO EXEMPLO E0001 9999 LISBOA'))->toBe('SERVICO EXEMPLO')
        ->and(Rule::suggestKeyword('TRF P/ ASSOCIACAO MARIA EXEMPLO SILVA'))->toBe('ASSOCIACAO MARIA')
        ->and(Rule::suggestKeyword('12 34'))->toBe('12 34');
});

it('opens the rule form filled in from a transaction', function () {
    ['ana' => $ana, 'account' => $account] = household();
    $t = move($account, -2000, '2026-11-03', 'COMPRA 1234 PADARIA CENTRAL LISBOA');
    $t->update(['category_id' => category('Groceries')->id, 'owner_id' => $ana->id]);

    $this->get('/rules?from='.$t->id)->assertOk()
        ->assertSee('value="PADARIA CENTRAL"', false)
        ->assertSee('COMPRA 1234 PADARIA CENTRAL LISBOA');
});

it('tells how many transactions have no category yet', function () {
    ['account' => $account] = household();
    move($account, -100, '2026-11-03');
    move($account, -200, '2026-11-03');

    $this->get('/transactions')->assertOk()->assertSee('2 movimentos ainda não têm categoria');
});

it('writes a database backup and keeps only the newest ones', function () {
    Storage::fake('local');
    household();
    DB::rollBack(); // VACUUM cannot run inside the transaction the test wrapped around us

    $backup = new Backup;
    foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $stamp) {
        Storage::disk('local')->put("backups/tostao-{$stamp}.sqlite", 'old');
    }

    $path = $backup->run(keep: 2);

    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBeGreaterThan(1000)
        ->and(Storage::disk('local')->files('backups'))->toHaveCount(2);
});
