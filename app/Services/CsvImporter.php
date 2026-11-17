<?php

namespace App\Services;

use App\Support\DateParser;
use App\Support\Money;

/**
 * Turns the text of a bank export into normalised rows.
 *
 * Banks disagree on everything: delimiter, encoding, date format, decimal
 * separator, a single signed amount column or separate debit/credit columns.
 * This class detects what it can and lets the user override the rest.
 */
class CsvImporter
{
    public const DELIMITERS = [';' => ';', ',' => ',', "\t" => 'tab', '|' => '|'];

    /** @return string text in UTF-8 without a BOM */
    public function normalise(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return str_replace(["\r\n", "\r"], "\n", $raw);
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

        $headerIndex = array_key_first($all);

        foreach ($all as $index => $cells) {
            if ($hasHeader && $index === $headerIndex) {
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
