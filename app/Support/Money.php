<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money helpers. Everything in the database is an integer number of cents.
 */
final class Money
{
    /**
     * Parse the many ways banks and humans write an amount into cents.
     *
     * Understands "-5,50 €", "1.234,56", "1,234.56", "(12.30)", "+40,00 €",
     * "1186.35" and "12,3". Returns null when the text is not an amount.
     *
     * @param  string  $decimal  "auto", "comma" or "dot". In "auto" mode a lone
     *                           separator followed by exactly three digits is
     *                           read as a thousands separator ("1.234" = 1234).
     */
    public static function parse(?string $text, string $decimal = 'auto'): ?int
    {
        if ($text === null) {
            return null;
        }

        $s = trim(str_replace(["\u{00A0}", "\u{202F}", ' '], '', $text));
        $s = preg_replace('/[€$£]|EUR|USD|GBP/iu', '', $s);

        if ($s === '' || $s === null) {
            return null;
        }

        $negative = false;
        if (preg_match('/^\((.*)\)$/', $s, $m)) {
            $negative = true;
            $s = $m[1];
        }
        if (str_starts_with($s, '-') || str_starts_with($s, '−')) {
            $negative = true;
            $s = ltrim($s, '-−');
        } elseif (str_ends_with($s, '-')) {
            $negative = true;
            $s = rtrim($s, '-');
        }
        $s = ltrim($s, '+');

        if (! preg_match('/^[\d.,]+$/', $s) || ! preg_match('/\d/', $s)) {
            return null;
        }

        $decimalChar = self::decimalSeparator($s, $decimal);
        if ($decimalChar === null) {
            $integer = preg_replace('/[.,]/', '', $s);
            $fraction = '';
        } else {
            $pos = strrpos($s, $decimalChar);
            $integer = preg_replace('/[.,]/', '', substr($s, 0, $pos));
            $fraction = substr($s, $pos + 1);
        }

        if ($integer === '') {
            $integer = '0';
        }
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $cents = ((int) $integer) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private static function decimalSeparator(string $s, string $mode): ?string
    {
        $hasDot = str_contains($s, '.');
        $hasComma = str_contains($s, ',');

        if ($mode === 'comma') {
            return $hasComma ? ',' : null;
        }
        if ($mode === 'dot') {
            return $hasDot ? '.' : null;
        }

        if ($hasDot && $hasComma) {
            return strrpos($s, '.') > strrpos($s, ',') ? '.' : ',';
        }

        $sep = $hasComma ? ',' : ($hasDot ? '.' : null);
        if ($sep === null) {
            return null;
        }

        $parts = explode($sep, $s);
        $last = end($parts);
        if (count($parts) > 2) {
            return null; // 1.234.567 is only thousands
        }

        return strlen($last) === 3 ? null : $sep;
    }

    /** "1.234,50 €" in Portuguese, "€1,234.50" in English. */
    public static function format(int $cents, bool $symbol = true): string
    {
        $locale = str_replace('_', '-', app()->getLocale());
        $locale = $locale === 'pt' ? 'pt-PT' : $locale;
        $value = $cents / 100;

        if (class_exists(NumberFormatter::class)) {
            $fmt = new NumberFormatter($locale, $symbol ? NumberFormatter::CURRENCY : NumberFormatter::DECIMAL);
            if (! $symbol) {
                $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
                $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
            }
            $out = $symbol ? $fmt->formatCurrency($value, config('tostao.currency')) : $fmt->format($value);
            if ($out !== false) {
                // Keep the number and the symbol on one line: use a plain no-break space.
                return str_replace("\u{202F}", "\u{00A0}", $out);
            }
        }

        return number_format($value, 2, ',', '.').($symbol ? ' €' : '');
    }

    /** Value for an <input>: always "12.50" style so it round-trips through parse(). */
    public static function input(int $cents): string
    {
        return number_format(abs($cents) / 100, 2, '.', '');
    }
}
