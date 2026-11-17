<?php

namespace App\Statements;

use Smalot\PdfParser\Parser;
use Throwable;

/** Turns a PDF bank statement into the same rows the CSV import produces. */
class PdfStatementReader
{
    /** @return list<StatementParser> */
    private function parsers(): array
    {
        return [new MillenniumCombinedParser];
    }

    public static function isPdf(string $bytes): bool
    {
        return str_starts_with($bytes, '%PDF');
    }

    /**
     * @return array<string, mixed>|null null when no known layout recognises the file
     */
    public function read(string $bytes): ?array
    {
        try {
            $text = (new Parser)->parseContent($bytes)->getText();
        } catch (Throwable) {
            return null;
        }

        foreach ($this->parsers() as $parser) {
            if ($parser->supports($text)) {
                return ['bank' => $parser->label()] + $parser->parse($text);
            }
        }

        return null;
    }
}
