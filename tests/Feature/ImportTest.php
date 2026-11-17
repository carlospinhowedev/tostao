<?php

use App\Models\Rule;
use App\Models\Transaction;
use App\Services\CsvImporter;
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

/** A statement shaped like the ones Millennium bcp exports: UTF-16 without a BOM, account details above the header. Invented content. */
function utf16Statement(): string
{
    $text = "banco-exemplo.pt\r\nSaldos e movimentos\r\nEXTRATO DA CONTA À ORDEM Nº;00000000001\r\nMoeda base:;EUR\r\nSaldo disponível:;100.00\r\nData de:;01-11-2026\r\nData até:;17-11-2026\r\n\r\n"
        ."Data lançamento;Data valor;Descrição;Montante;Tipo;Saldo\r\n"
        ."16-11-2026;16-11-2026;COMPRA 0000 PADARIA CENTRAL;-3.40;Débito;100.00\r\n"
        ."10-11-2026;10-11-2026;TRF P/ EMPRESA EXEMPLO LDA;1250.00;Crédito;103.40\r\n"
        ."03-11-2026;03-11-2026;PAGAMENTO SERVIÇOS;-1080.00;Débito;-1146.60\r\n"
        ."O banco-exemplo.pt é um serviço do Banco Exemplo S.A.\r\n";

    return mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
}

it('imports a UTF-16 statement with account details above the header', function () {
    ['account' => $account] = household();
    $path = tempnam(sys_get_temp_dir(), 'utf16');
    file_put_contents($path, utf16Statement());

    $response = $this->post('/import', [
        'account_id' => $account->id,
        'file' => new UploadedFile($path, 'MOVS.csv', 'text/csv', null, true),
    ]);
    $response->assertRedirect();

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee('PADARIA CENTRAL')
        ->assertSee('EMPRESA EXEMPLO LDA')
        ->assertSee('PAGAMENTO SERVIÇOS')
        ->assertDontSee('Saldos e movimentos');
});

it('reads the header, delimiter, dates and dot decimals of that statement', function () {
    $importer = new CsvImporter;
    $text = $importer->normalise(utf16Statement());
    $rows = $importer->readRows($text, $importer->detectDelimiter($text));
    $header = $importer->headerKey($rows);
    $map = $importer->guessMapping($rows[$header]);
    $result = $importer->parse($text, ';', true, $map, 'd-m-Y');

    expect($importer->detectDelimiter($text))->toBe(';')
        ->and($map)->toBe(['date' => 0, 'description' => 2, 'amount' => 3, 'debit' => null, 'credit' => null])
        ->and($result['skipped'])->toBe([])
        ->and(array_column($result['rows'], 'amount_cents'))->toBe([-340, 125000, -108000])
        ->and($result['rows'][0]['date'])->toBe('2026-11-16');
});
