<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

final class DateParser
{
    public const FORMATS = [
        'd/m/Y' => 'DD/MM/YYYY',
        'd-m-Y' => 'DD-MM-YYYY',
        'd.m.Y' => 'DD.MM.YYYY',
        'Y-m-d' => 'YYYY-MM-DD',
        'm/d/Y' => 'MM/DD/YYYY',
        'd/m/y' => 'DD/MM/YY',
    ];

    /** Returns "Y-m-d" or null if the text is not a valid date in that format. */
    public static function parse(?string $text, string $format = 'd/m/Y'): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $text);
        } catch (InvalidFormatException) {
            return null;
        }

        $errors = CarbonImmutable::getLastErrors();

        if ($date === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /** Pick the most plausible format by testing sample values. */
    public static function guessFormat(array $samples): string
    {
        $best = 'd/m/Y';
        $bestScore = -1;

        foreach (array_keys(self::FORMATS) as $format) {
            $score = 0;
            foreach ($samples as $sample) {
                if (self::parse($sample, $format) !== null) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $best = $format;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
