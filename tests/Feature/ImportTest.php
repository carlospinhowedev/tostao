<?php

use App\Models\Rule;
use App\Models\Transaction;
use App\Services\CsvImporter;
use App\Services\ImportService;
use App\Statements\PdfStatementReader;
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

/** A reader that returns a canned statement, so the flow can be tested without a real PDF. */
function fakePdfReader(): void
{
    app()->instance(PdfStatementReader::class, new class extends PdfStatementReader
    {
        public function read(string $bytes): ?array
        {
            return [
                'bank' => 'Banco Exemplo, extrato combinado',
                'rows' => [
                    ['line' => 10, 'date' => '2026-03-01', 'description' => 'COMPRA 1234 PADARIA CENTRAL', 'amount_cents' => -1250],
                    ['line' => 12, 'date' => '2026-03-02', 'description' => 'TRF. P/O EMPRESA EXEMPLO LDA', 'amount_cents' => 150000],
                ],
                'skipped' => [], 'opening_cents' => 100000, 'closing_cents' => 248750,
                'from' => '2026-03-01', 'to' => '2026-03-31', 'balanced' => true,
            ];
        }
    });
}

it('imports a PDF statement through the same review step as a CSV', function () {
    ['account' => $account] = household();
    fakePdfReader();

    $response = $this->post('/import', [
        'account_id' => $account->id,
        'file' => UploadedFile::fake()->createWithContent('extrato.pdf', "%PDF-1.4\nfake"),
    ]);
    $response->assertRedirect();
    $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

    Storage::disk('local')->assertExists("imports/{$token}.pdf");
    $this->get($response->headers->get('Location'))->assertOk()
        ->assertSee('Extrato lido do PDF')->assertSee('PADARIA CENTRAL')->assertSee('Conferido');

    $this->post("/import/{$token}", ['account_id' => $account->id, 'rows' => [10, 12]])->assertRedirect('/transactions');

    expect(Transaction::where('account_id', $account->id)->orderBy('date')->pluck('amount_cents')->all())->toBe([-1250, 150000]);
    Storage::disk('local')->assertMissing("imports/{$token}.pdf");

    // Importing the same statement again adds nothing.
    $again = $this->post('/import', ['account_id' => $account->id, 'file' => UploadedFile::fake()->createWithContent('extrato.pdf', "%PDF-1.4\nfake")]);
    $token2 = basename(parse_url($again->headers->get('Location'), PHP_URL_PATH));
    $this->post("/import/{$token2}", ['account_id' => $account->id, 'rows' => [10, 12]]);
    expect(Transaction::count())->toBe(2);
});

it('says so when a PDF is not a layout it can read', function () {
    ['account' => $account] = household();

    $this->post('/import', ['account_id' => $account->id, 'file' => UploadedFile::fake()->createWithContent('x.pdf', "%PDF-1.4\nnot a statement")])
        ->assertSessionHasErrors('file');
    expect(Storage::disk('local')->files('imports'))->toBe([]);
});

it('flags rows that overlap an earlier import worded differently, instead of doubling them up', function () {
    ['account' => $account] = household();
    $service = app(ImportService::class);

    // A mid-month export, imported earlier...
    $first = $service->prepare($account, [
        ['line' => 1, 'date' => '2026-10-05', 'description' => 'COMPRA 1234 PADARIA CENTRAL', 'amount_cents' => -840],
        ['line' => 2, 'date' => '2026-10-07', 'description' => 'TRF P/ ANA EXEMPLO', 'amount_cents' => -2000],
    ]);
    $service->commit($account, $first);

    // ...and the full-month statement, where the bank spells the same movements slightly differently.
    $second = $service->prepare($account, [
        ['line' => 1, 'date' => '2026-10-05', 'description' => 'COMPRA 1234 PADARIA CENTRAL CONTACTLESS', 'amount_cents' => -840],
        ['line' => 2, 'date' => '2026-10-07', 'description' => 'TRF P/ ANA EXEMPLO', 'amount_cents' => -2000],
        ['line' => 3, 'date' => '2026-10-20', 'description' => 'COMPRA 1234 OUTRA LOJA', 'amount_cents' => -500],
    ]);

    expect(array_column($second, 'status'))->toBe(['maybe', 'duplicate', 'new']);
});

it('keeps legitimate repeats: three identical coffees are one duplicate each, not all maybes', function () {
    ['account' => $account] = household();
    $service = app(ImportService::class);
    $coffee = fn ($line) => ['line' => $line, 'date' => '2026-10-05', 'description' => 'CAFE CENTRAL', 'amount_cents' => -120];

    $service->commit($account, $service->prepare($account, [$coffee(1), $coffee(2)]));
    $again = $service->prepare($account, [$coffee(1), $coffee(2), $coffee(3)]);

    // Two were imported before and match exactly; only the third is new.
    expect(array_column($again, 'status'))->toBe(['duplicate', 'duplicate', 'new']);
});

it('counts a manual entry only once against the rows of a statement', function () {
    ['account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'date' => '2026-10-05', 'description' => 'typed by hand', 'amount_cents' => -840]);

    $rows = [
        ['line' => 1, 'date' => '2026-10-05', 'description' => 'COMPRA A', 'amount_cents' => -840],
        ['line' => 2, 'date' => '2026-10-05', 'description' => 'COMPRA B', 'amount_cents' => -840],
    ];

    // One of the two can be the manual entry; the other cannot, so it is genuinely new.
    expect(array_column(app(ImportService::class)->prepare($account, $rows), 'status'))->toBe(['maybe', 'new']);
});
