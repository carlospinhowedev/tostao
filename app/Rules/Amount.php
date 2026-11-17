<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A positive money amount typed by a human ("12,50", "1.234,56", "12.5"). */
class Amount implements ValidationRule
{
    public function __construct(private bool $allowZero = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cents = Money::parse((string) $value);

        if ($cents === null || ($cents === 0 && ! $this->allowZero)) {
            $fail(__('Enter a valid amount, for example 12,50.'));
        }
    }
}
