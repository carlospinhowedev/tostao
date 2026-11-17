<?php

namespace App\Services;

use App\Support\DateParser;
use App\Support\Money;

/**
 * Turns the text of a bank export into normalised rows.
 *
 * Banks disagree on everything: delimiter, encoding, date format, decimal
 * separator, a single signed amount column or separate debit/credit columns,
 * and how many lines of account details come before the transactions.
 * This class detects what it can and lets the user override the rest.
 */
class CsvImporter
{
    public const DELIMITERS = [';' => ';', ',' => ',', "\t" => 'tab', '|' => '|'];

    /** @return string text in UTF-8 without a BOM */
    public function normalise(string $raw): string
    {
        $raw = $this->fromUtf16($raw) ?? $raw;
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return str_replace(["\r\n", "\r"], "\n", $raw);
    }

    /**
     * Some banks (Millennium bcp, for one) export UTF-16, often with no byte
     * order mark. Mostly-ASCII text in two bytes per character is full of NUL
     * bytes: all in the odd positions for little-endian, the even ones for
     * big-endian. Returns null when the text is not UTF-16.
     */
    private function fromUtf16(string $raw): ?string
    {
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }

        $sample = substr($raw, 0, 400);
        $length = strlen($sample);
        if ($length < 4 || ! str_contains($sample, "\0")) {
            return null;
        }

        $odd = $even = 0;
        for ($i = 0; $i < $length; $i++) {
            if ($sample[$i] === "\0") {
                $i % 2 === 0 ? $even++ : $odd++;
            }
        }

        $half = $length / 2;
        if ($odd > $half * 0.6 && $even < $half * 0.1) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }
        if ($even > $half * 0.6 && $odd < $half * 0.1) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        }

        return null;
    }

    public function detectDelimiter(string $text): string
    {
        $lines = array_slice(array_values(array_filter(explode("\n", $text), fn ($l) => trim($l) !== '')), 0, 10);
        $best = ';';
        $bestScore = -1;

        foreach (array_keys(self::DELIMITERS) as $delimiter) {
            $counts = array_map(fn ($l) => substr_count($l, $delimiter), $lines);
            if (! $counts || min($counts) === 0) {
                $score = max($counts ?: [0]) > 0 ? 0.5 : 0;
            } else {
                $score = min($counts) + (count(array_unique($counts)) === 1 ? 100 : 0);
            }
            if ($score > $bestScore) {
                $best = $delimiter;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Non-blank rows, keyed by their zero-based line in the file so messages
     * can point at the real line.
     *
     * @return array<int, list<string>>
     */
    public function readRows(string $text, string $delimiter): array
    {
        $rows = [];
        foreach (explode("\n", $text) as $i => $line) {
            if (trim($line) === '' || trim($line, $delimiter." \t") === '') {
                continue;
            }
            $rows[$i] = array_map(fn ($c) => trim((string) $c), str_getcsv($line, $delimiter, '"', ''));
        }

        return $rows;
    }

    /**
     * The key of the header row. Many banks put a few lines of account details
     * above it (name, balance, period), so the header is the first row that
     * names both a date column and an amount column. Null when none does.
     *
     * @param  array<int, list<string>>  $rows  as returned by readRows()
     */
    public function headerKey(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 40, true) as $key => $cells) {
            if (count($cells) < 3) {
                continue;
            }

            $map = $this->guessMapping($cells);
            if ($map['date'] !== null && ($map['amount'] !== null || $map['debit'] !== null || $map['credit'] !== null)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Guess which column is what, from the header names (or the content).
     *
     * @param  list<string>  $header
     * @return array{date: ?int, description: ?int, amount: ?int, debit: ?int, credit: ?int}
     */
    public function guessMapping(array $header): array
    {
        $find = function (array $patterns, array $exclude = []) use ($header) {
            foreach ($patterns as $pattern) {
                foreach ($header as $i => $name) {
                    $name = mb_strtolower($name);
                    if (preg_match($pattern, $name) && ! array_filter($exclude, fn ($e) => preg_match($e, $name))) {
                        return $i;
                    }
                }
            }

            return null;
        };

        return [
            'date' => $find(['/lan[cç]amento|booking|posting/u', '/^data$|^date$|data mov|data op/u', '/data|date/u']),
            'description' => $find(['/descri|detalhe|memo|narrat|movimento|payee|benefici/u']),
            'amount' => $find(['/montante|amount|quantia|^valor$/u'], ['/data|date/u', '/saldo|balance/u']),
            'debit' => $find(['/d[ée]bito|debit/u']),
            'credit' => $find(['/cr[ée]dito|credit/u']),
        ];
    }

    /**
     * Parse rows according to a mapping.
     *
     * @param  array{date: ?int, description: ?int, amount: ?int, debit: ?int, credit: ?int}  $map
     * @return array{rows: list<array{line: int, date: string, description: string, amount_cents: int}>, skipped: list<array{line: int, reason: string}>}
     */
    public function parse(string $text, string $delimiter, bool $hasHeader, array $map, string $dateFormat, string $decimal = 'auto'): array
    {
        $all = $this->readRows($text, $delimiter);
        $rows = [];
        $skipped = [];

        $headerIndex = $this->headerKey($all) ?? array_key_first($all);

        foreach ($all as $index => $cells) {
            // Everything up to and including the header is not a transaction.
            if ($hasHeader && $index <= $headerIndex) {
                continue;
            }
            // Titles and footers are one cell wide; real rows never are.
            if (count($cells) < 2) {
                continue;
            }
            $line = $index + 1;

            $date = DateParser::parse($cells[$map['date']] ?? null, $dateFormat);
            if ($date === null) {
                $skipped[] = ['line' => $line, 'reason' => 'date'];

                continue;
            }

            $amount = $this->amountFor($cells, $map, $decimal);
            if ($amount === null || $amount === 0) {
                $skipped[] = ['line' => $line, 'reason' => 'amount'];

                continue;
            }

            $description = trim(preg_replace('/\s+/u', ' ', $cells[$map['description']] ?? ''));
            $rows[] = [
                'line' => $line,
                'date' => $date,
                'description' => $description !== '' ? $description : '—',
                'amount_cents' => $amount,
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /** @param array{amount: ?int, debit: ?int, credit: ?int} $map */
    private function amountFor(array $cells, array $map, string $decimal): ?int
    {
        if ($map['amount'] !== null) {
            return Money::parse($cells[$map['amount']] ?? null, $decimal);
        }

        $debit = $map['debit'] !== null ? Money::parse($cells[$map['debit']] ?? null, $decimal) : null;
        $credit = $map['credit'] !== null ? Money::parse($cells[$map['credit']] ?? null, $decimal) : null;

        if ($debit !== null && $debit !== 0) {
            return -abs($debit);
        }

        return $credit !== null ? abs($credit) : null;
    }
}
