<?php

use App\Services\CsvImporter;

beforeEach(function () {
    $this->importer = new CsvImporter;
    $this->text = $this->importer->normalise(file_get_contents(base_path('tests/Fixtures/extrato-tabulado.tsv')));
});

it('detects a tab delimiter even though amounts contain commas', function () {
    expect($this->importer->detectDelimiter($this->text))->toBe("\t");
});

it('detects semicolon and comma files', function () {
    expect($this->importer->detectDelimiter("data;desc;valor\n01/01/2026;Cafe;-1,20\n"))->toBe(';');
    expect($this->importer->detectDelimiter("date,desc,amount\n2026-01-01,Coffee,-1.20\n"))->toBe(',');
});

it('guesses the columns from Portuguese headers and prefers the booking date', function () {
    $header = array_values($this->importer->readRows($this->text, "\t"))[0];

    expect($this->importer->guessMapping($header))->toBe([
        'date' => 0, 'description' => 2, 'amount' => 3, 'debit' => null, 'credit' => null,
    ]);
});

it('parses a real-looking statement, skipping blank rows and keeping identical rows', function () {
    $map = ['date' => 0, 'description' => 2, 'amount' => 3, 'debit' => null, 'credit' => null];
    $result = $this->importer->parse($this->text, "\t", true, $map, 'd/m/Y');

    expect($result['rows'])->toHaveCount(7)
        ->and($result['skipped'])->toBe([])
        ->and($result['rows'][1]['description'])->toBe('COMPRA 1234 CAFE CENTRAL CONTACTLESS')
        ->and($result['rows'][1]['amount_cents'])->toBe(-140)
        ->and($result['rows'][2]['amount_cents'])->toBe(-140)
        ->and($result['rows'][4]['amount_cents'])->toBe(118635) // "1.186,35" style without euro sign
        ->and($result['rows'][0]['date'])->toBe('2026-11-16');
});

it('reports rows it could not read instead of dropping them silently', function () {
    $map = ['date' => 0, 'description' => 1, 'amount' => 2, 'debit' => null, 'credit' => null];
    $result = $this->importer->parse("data;desc;valor\n99/99/2026;x;1,00\n01/01/2026;y;abc\n01/01/2026;ok;2,00\n", ';', true, $map, 'd/m/Y');

    expect($result['rows'])->toHaveCount(1)
        ->and(array_column($result['skipped'], 'reason'))->toBe(['date', 'amount']);
});

it('supports separate debit and credit columns', function () {
    $map = ['date' => 0, 'description' => 1, 'amount' => null, 'debit' => 2, 'credit' => 3];
    $text = "Date;Description;Debit;Credit\n2026-01-02;Rent;500,00;\n2026-01-03;Salary;;1.200,00\n";
    $result = $this->importer->parse($text, ';', true, $map, 'Y-m-d');

    expect(array_column($result['rows'], 'amount_cents'))->toBe([-50000, 120000]);
});

it('converts legacy Windows-1252 files to UTF-8', function () {
    $latin1 = mb_convert_encoding("data;descrição;valor\n01/01/2026;Café;-1,20\n", 'Windows-1252', 'UTF-8');

    expect($this->importer->normalise($latin1))->toContain('descrição')->toContain('Café');
});

it('strips a UTF-8 byte order mark', function () {
    expect($this->importer->normalise("\xEF\xBB\xBFdata;valor"))->toBe('data;valor');
});
