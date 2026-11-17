<?php

use App\Statements\MillenniumCombinedParser;
use App\Statements\PdfStatementReader;

/** Text shaped like what the PDF reader returns for a Millennium "Extrato combinado". Every figure is invented. */
function statementText(string $period = ' 2026/03/01 A 2026/03/31', ?array $rows = null, string $closing = '40.00'): string
{
    $rows ??= [
        ['3.01 3.01', "COMPRA 1234 PADARIA CENTRAL CONTACTLESS\t12.50 987.50"],
        ['3.02 3.01', "TRF. P/O EMPRESA EXEMPLO LDA\t1 500.00 2 487.50"],
        ['3.05 3.05', "PAG.PRESTACAO N. 001 EMP.N.   9999999999\t647.95 1 839.55"],
        ['PAGEBREAK', ''],
        ['3.10 3.10', "LEV ATM 1234 BANCO EXEMPLO\t2 000.00 160.45 -"],
        ['3.12 3.12', "TRF. P/O ANA EXEMPLO\t200.45 40.00"],
    ];

    $lines = ['EXTRATO COMBINADO', 'N. 2026/003', 'Banco Exemplo, S.A.', 'CONTA MILLENNIUM          N. 0000000000          MOEDA: EUR', 'EXTRATO DE', $period,
        'DATA', 'LANC.', 'DATA', 'VALORDESCRITIVO DEBITO CREDITO SALDO', "SALDO INICIAL\t1 000.00          "];

    foreach ($rows as [$dates, $detail]) {
        if ($dates === 'PAGEBREAK') {
            array_push($lines, "A TRANSPORTAR\t1 839.55", "26/03/31\tCONTA: 0000000000 PAG: 00002", 'DATA', 'VALORDESCRITIVO DEBITO CREDITO SALDO', "TRANSPORTE\t1 839.55");

            continue;
        }
        array_push($lines, $dates, $detail);
    }
    $lines[] = "SALDO FINAL\t{$closing}";
    $lines[] = "SALDO DISPONIVEL\t{$closing}";

    return implode("\n", $lines);
}

it('recognises a Millennium combined statement and nothing else', function () {
    $parser = new MillenniumCombinedParser;

    expect($parser->supports(statementText()))->toBeTrue()
        ->and($parser->supports("data;descricao;valor\n01/01/2026;x;1,00"))->toBeFalse();
});

it('reads the movements, the period and the balances', function () {
    $r = (new MillenniumCombinedParser)->parse(statementText());

    expect($r['from'])->toBe('2026-03-01')->and($r['to'])->toBe('2026-03-31')
        ->and($r['opening_cents'])->toBe(100000)->and($r['closing_cents'])->toBe(4000)
        ->and($r['rows'])->toHaveCount(5)
        ->and($r['rows'][0])->toMatchArray(['date' => '2026-03-01', 'description' => 'COMPRA 1234 PADARIA CENTRAL CONTACTLESS', 'amount_cents' => -1250])
        ->and($r['rows'][1]['amount_cents'])->toBe(150000)          // a credit, told apart by the balance going up
        ->and($r['rows'][2]['description'])->toBe('PAG.PRESTACAO N. 001 EMP.N. 9999999999')
        ->and($r['rows'][2]['amount_cents'])->toBe(-64795)           // the reference number is not taken for part of the amount
        ->and($r['rows'][3]['amount_cents'])->toBe(-200000)          // into overdraft: balance printed as "160.45 -"
        ->and($r['rows'][4]['amount_cents'])->toBe(20045);
});

it('checks that the movements add up to the printed closing balance', function () {
    $ok = (new MillenniumCombinedParser)->parse(statementText());
    expect($ok['balanced'])->toBeTrue()->and($ok['skipped'])->toBe([]);

    // A closing balance that does not match what the lines add up to is reported, not hidden.
    $bad = (new MillenniumCombinedParser)->parse(statementText(closing: '99.00'));
    expect($bad['balanced'])->toBeFalse();
});

it('does not guess when a line does not chain from the balance before it', function () {
    $rows = [['3.01 3.01', "COMPRA 1234 PADARIA\t12.50 987.50"], ['3.02 3.02', "COMPRA 1234 OUTRA\t10.00 500.00"]];
    $r = (new MillenniumCombinedParser)->parse(statementText(rows: $rows, closing: '500.00'));

    expect($r['rows'])->toHaveCount(1)->and($r['skipped'])->toHaveCount(1)->and($r['balanced'])->toBeFalse();
});

it('reads single-digit months, a period split over two lines, and December lines in a January statement', function () {
    $rows = [['12.31 12.31', "COMPRA 1234 PADARIA\t12.50 987.50"], ['1.02 1.02', "COMPRA 1234 OUTRA\t7.50 980.00"]];
    $r = (new MillenniumCombinedParser)->parse(statementText(period: ' 2027/01/01 A 2027/01/31', rows: $rows, closing: '980.00'));

    expect(array_column($r['rows'], 'date'))->toBe(['2026-12-31', '2027-01-02']);
});

it('joins a description that wraps onto the next line', function () {
    $text = str_replace("COMPRA 1234 PADARIA CENTRAL CONTACTLESS\t12.50 987.50", "COMPRA 1234 PADARIA CENTRAL\nCONTACTLESS\t12.50 987.50", statementText());
    $r = (new MillenniumCombinedParser)->parse($text);

    expect($r['rows'][0]['description'])->toBe('COMPRA 1234 PADARIA CENTRAL CONTACTLESS');
});

it('tells PDF bytes from text and returns nothing for a PDF it cannot read', function () {
    expect(PdfStatementReader::isPdf("%PDF-1.4\n..."))->toBeTrue()
        ->and(PdfStatementReader::isPdf("data;valor\n"))->toBeFalse()
        ->and((new PdfStatementReader)->read("%PDF-1.4\nnot really a pdf"))->toBeNull();
});
