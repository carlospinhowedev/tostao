<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** "shared" (the household) or the id of an existing member. */
class OwnerChoice implements ValidationRule
{
    public function __construct(private bool $allowEmpty = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->allowEmpty && ($value === null || $value === '')) {
            return;
        }

        if ($value === 'shared') {
            return;
        }

        if (! is_numeric($value) || ! User::whereKey((int) $value)->exists()) {
            $fail(__('Choose a member of the household.'));
        }
    }
}
