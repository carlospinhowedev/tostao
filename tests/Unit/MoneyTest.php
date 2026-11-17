<?php

use App\Support\DateParser;
use App\Support\Money;

it('parses amounts written the way banks and humans write them', function (?string $text, ?int $cents) {
    expect(Money::parse($text))->toBe($cents);
})->with([
    'negative with euro sign' => ['-5,50 €', -550],
    'thousands dot and decimal comma' => ['1.234,56', 123456],
    'thousands comma and decimal dot' => ['1,234.56', 123456],
    'plain dot decimal' => ['1186.35', 118635],
    'plus sign' => ['+40,00 €', 4000],
    'accounting negative' => ['(12.30)', -1230],
    'single decimal digit' => ['12,3', 1230],
    'integer' => ['25', 2500],
    'thousands only' => ['1.234', 123400],
    'non breaking space' => ["-1\u{00A0}234,56 EUR", -123456],
    'trailing minus' => ['5,50-', -550],
    'empty' => ['', null],
    'text' => ['abc', null],
    'null' => [null, null],
]);

it('can be told which separator is the decimal one', function () {
    expect(Money::parse('1.234', 'dot'))->toBe(123);
    expect(Money::parse('1,234', 'comma'))->toBe(123);
});

it('formats cents for the active locale', function () {
    app()->setLocale('pt');
    $pt = preg_replace('/\s+/u', ' ', Money::format(-123456));
    expect($pt)->toMatch('/^-1.?234,56 €$/u');

    app()->setLocale('en');
    expect(Money::format(123456))->toContain('1,234.56');
});

it('round-trips input values', function () {
    expect(Money::parse(Money::input(-1250)))->toBe(1250);
});

it('parses dates by format and rejects impossible ones', function () {
    expect(DateParser::parse('16/11/2026', 'd/m/Y'))->toBe('2026-11-16');
    expect(DateParser::parse('2026-11-16', 'Y-m-d'))->toBe('2026-11-16');
    expect(DateParser::parse('31/02/2026', 'd/m/Y'))->toBeNull();
    expect(DateParser::parse('', 'd/m/Y'))->toBeNull();
});

it('guesses the date format from samples', function () {
    expect(DateParser::guessFormat(['16/11/2026', '13/11/2026']))->toBe('d/m/Y');
    expect(DateParser::guessFormat(['2026-11-16', '2026-11-13']))->toBe('Y-m-d');
});
