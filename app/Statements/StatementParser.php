<?php

namespace App\Statements;

/**
 * Reads the text of one kind of bank PDF statement. Each bank lays its PDF out
 * differently, so each layout is its own class; PdfStatementReader asks them in
 * turn which one recognises the text.
 */
interface StatementParser
{
    /** Name shown to people ("Millennium bcp, extrato combinado"). */
    public function label(): string;

    public function supports(string $text): bool;

    /** @return array{rows: list<array{line: int, date: string, description: string, amount_cents: int}>, opening_cents: ?int, closing_cents: ?int, from: ?string, to: ?string, balanced: bool, skipped: list<array{line: int, reason: string}>} */
    public function parse(string $text): array;
}
