<?php

namespace App\Statements;

/**
 * Millennium bcp "Extrato combinado" (the monthly PDF).
 *
 * The movements of the current account sit between "SALDO INICIAL" and "SALDO
 * FINAL". Each one is a line with two dates (month.day) followed by the
 * description, one amount and the running balance. The PDF has no separate
 * debit and credit columns once it is turned into text, so the sign comes from
 * the balance: if the balance went down by the amount it was money out, if it
 * went up, money in. That also lets us check the whole month adds up to the
 * closing balance printed on the statement.
 *
 * Other sections of the PDF (loan instalments, credit card, notices) are not
 * movements of this account and are ignored.
 */
class MillenniumCombinedParser implements StatementParser
{
    public function label(): string
    {
        return 'Millennium bcp, extrato combinado';
    }

    public function supports(string $text): bool
    {
        return str_contains($text, 'EXTRATO COMBINADO') && str_contains($text, 'SALDO INICIAL');
    }

    public function parse(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        [$from, $to] = $this->period($text);
        $endMonth = $to ? (int) substr($to, 5, 2) : null;
        $endYear = $to ? (int) substr($to, 0, 4) : (int) date('Y');

        $rows = [];
        $skipped = [];
        $opening = $closing = null;
        $balance = null;
        $inside = false;
        $pending = null;       // [month, day] of a movement whose line has not come yet
        $partial = '';         // description spread over several lines

        foreach ($lines as $i => $raw) {
            $line = trim(preg_replace('/\s+/u', ' ', str_replace("\t", ' ', $raw)));

            if (! $inside) {
                if (preg_match('/^SALDO INICIAL\s+(-?[\d ]+\.\d{2} ?-?)\s*$/u', $line, $m)) {
                    $opening = $balance = $this->cents($m[1]);
                    $inside = true;
                }

                continue;
            }

            if (preg_match('/^SALDO FINAL\s+(-?[\d ]+\.\d{2} ?-?)\s*$/u', $line, $m)) {
                $closing = $this->cents($m[1]);

                break;
            }

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(\d{1,2})\.(\d{1,2}) (\d{1,2})\.(\d{1,2})$/', $line, $m)) {
                $pending = [(int) $m[1], (int) $m[2]];
                $partial = '';

                continue;
            }

            if ($pending === null) {
                continue; // page headers, carry-over lines, notices
            }

            // "<description> <amount> <balance>": the last two numbers are the amounts. An amount
            // never starts in the middle of a run of digits, so a reference number is not mistaken for one.
            if (preg_match('/^(.*?)\s*(?<!\d)(\d{1,3}(?: \d{3})*\.\d{2}) (-?\d{1,3}(?: \d{3})*\.\d{2} ?-?)$/u', $line, $m)) {
                $description = trim($partial.' '.$m[1]);
                $amount = $this->cents($m[2]);
                $new = $this->cents($m[3]);

                $signed = match (true) {
                    $balance - $amount === $new => -$amount,
                    $balance + $amount === $new => $amount,
                    default => null,
                };

                if ($signed === null || $description === '') {
                    $skipped[] = ['line' => $i + 1, 'reason' => 'balance'];
                } else {
                    $month = $pending[0];
                    $year = $endMonth !== null && $month > $endMonth ? $endYear - 1 : $endYear;
                    $rows[] = [
                        'line' => $i + 1,
                        'date' => sprintf('%04d-%02d-%02d', $year, $month, $pending[1]),
                        'description' => $description,
                        'amount_cents' => $signed,
                    ];
                }

                $balance = $new;
                $pending = null;
                $partial = '';

                continue;
            }

            // A description that wraps onto the next line.
            $partial = trim($partial.' '.$line);
        }

        return [
            'rows' => $rows,
            'opening_cents' => $opening,
            'closing_cents' => $closing,
            'from' => $from,
            'to' => $to,
            // True when every movement chained from the opening balance to the printed closing balance.
            'balanced' => $closing !== null && $balance === $closing && $skipped === [],
            'skipped' => $skipped,
        ];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function period(string $text): array
    {
        if (preg_match('/EXTRATO DE\s+(\d{4})\/(\d{2})\/(\d{2})\s+A\s+(\d{4})\/(\d{2})\/(\d{2})/', $text, $m)) {
            return ["{$m[1]}-{$m[2]}-{$m[3]}", "{$m[4]}-{$m[5]}-{$m[6]}"];
        }

        return [null, null];
    }

    /** "1 537.75" or "177 813.48-" to cents. */
    private function cents(string $value): int
    {
        $negative = str_contains($value, '-');
        $digits = preg_replace('/[^\d.]/', '', $value);
        [$whole, $fraction] = array_pad(explode('.', $digits), 2, '00');
        $cents = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }
}
