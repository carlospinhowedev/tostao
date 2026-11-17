<?php

use App\Models\Rule;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function uploadFixture($test, $account): string
{
    $response = $test->post('/import', [
        'account_id' => $account->id,
        'file' => new UploadedFile(base_path('tests/Fixtures/extrato-tabulado.tsv'), 'extrato.csv', 'text/csv', null, true),
    ]);

    $response->assertRedirect();

    return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
}

/** Everything the review form would post back for all 7 rows. */
function commitPayload(string $token, $account, array $extra = []): array
{
    return array_merge([
        'account_id' => $account->id,
        'delimiter' => 'tab',
        'has_header' => 1,
        'date_format' => 'd/m/Y',
        'decimal' => 'auto',
        'date' => 0, 'description' => 2, 'amount' => 3, 'debit' => '', 'credit' => '',
        'rows' => [2, 3, 4, 5, 7, 8, 9], // line numbers (blank row 6 is skipped)
    ], $extra);
}

beforeEach(function () {
    Storage::fake('local');
});

it('detects the Portuguese bank layout and previews every row', function () {
    ['account' => $account] = household();
    $token = uploadFixture($this, $account);

    $this->get("/import/{$token}?account_id={$account->id}")
        ->assertOk()
        ->assertSee('CAFE CENTRAL')
        ->assertSee('PRESTACAO')
        ->assertSee('-512,30')
        ->assertSee('186,35')
        ->assertSee('Novo');
});

it('imports the selected rows with the right dates and cents', function () {
    ['account' => $account] = household();
    $token = uploadFixture($this, $account);

    $this->post("/import/{$token}", commitPayload($token, $account))->assertRedirect('/transactions');

    expect(Transaction::count())->toBe(7)
        ->and(Transaction::where('description', 'like', '%CAFE CENTRAL%')->count())->toBe(2) // identical rows are kept
        ->and(Transaction::where('description', 'like', '%VENCIMENTO%')->value('amount_cents'))->toBe(118635)
        ->and(Transaction::where('description', 'like', '%PRESTACAO%')->value('amount_cents'))->toBe(-51230)
        ->and(Transaction::orderBy('date')->first()->date->format('Y-m-d'))->toBe('2026-11-11');
    Storage::disk('local')->assertMissing("imports/{$token}.csv");
});

it('flags rows already imported so importing twice is safe', function () {
    ['account' => $account] = household();
    $first = uploadFixture($this, $account);
    $this->post("/import/{$first}", commitPayload($first, $account));

    $second = uploadFixture($this, $account);
    $page = $this->get("/import/{$second}?account_id={$account->id}")->assertSee('Já importado')->getContent();
    // All seven rows, including the ones on the last day of the range, are recognised.
    expect(substr_count($page, 'Já importado'))->toBe(7);

    // Even if someone forces the rows through, nothing is duplicated.
    $this->post("/import/{$second}", commitPayload($second, $account));
    expect(Transaction::count())->toBe(7);
});

it('suggests category and person from the rules', function () {
    ['ana' => $ana, 'account' => $account] = household();
    Rule::create(['pattern' => 'cafe central', 'category_id' => category('Eating out')->id, 'set_owner' => true, 'owner_id' => $ana->id]);

    $token = uploadFixture($this, $account);
    $page = $this->get("/import/{$token}?account_id={$account->id}")->getContent();

    $cat = category('Eating out')->id;
    expect($page)->toMatch('/name="category\[3\]".*?<option value="'.$cat.'" selected/s');
});

it('applies the rule when the rows are committed with the suggested values', function () {
    ['ana' => $ana, 'account' => $account] = household();
    $cat = category('Eating out')->id;
    $token = uploadFixture($this, $account);

    $this->post("/import/{$token}", commitPayload($token, $account, [
        'category' => [3 => $cat], 'owner' => [3 => (string) $ana->id],
    ]));

    $t = Transaction::where('description', 'like', '%CAFE CENTRAL%')->first();
    expect($t->category_id)->toBe($cat)->and($t->owner_id)->toBe($ana->id);
});

it('refuses to import with no rows selected', function () {
    ['account' => $account] = household();
    $token = uploadFixture($this, $account);

    $this->post("/import/{$token}", commitPayload($token, $account, ['rows' => []]))->assertSessionHasErrors('rows');
});

it('does not accept a token that is not a real upload', function () {
    ['account' => $account] = household();

    $this->get("/import/not-a-uuid?account_id={$account->id}")->assertNotFound();
    $this->get('/import/'.Str::uuid()."?account_id={$account->id}")->assertNotFound();
});

it('rejects files that are not text tables', function () {
    ['account' => $account] = household();

    $this->post('/import', ['account_id' => $account->id, 'file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');
});
