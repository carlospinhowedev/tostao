<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pattern', 'exclude_pattern', 'amount_multiple_cents', 'category_id', 'owner_id', 'set_owner', 'priority'])]
class Rule extends Model
{
    protected function casts(): array
    {
        return ['set_owner' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * A starting keyword for a rule built from a transaction: the description
     * without the parts that change every time (card numbers, store codes) and
     * without the generic opening words banks put in front.
     */
    public static function suggestKeyword(string $description): string
    {
        $generic = ['COMPRA', 'LEV', 'ATM', 'TRF', 'TRF.', 'P/', 'P/O', 'PAGAMENTO', 'PAG', 'DEBITO', 'DD', 'EST', 'COM.', 'COM'];

        $words = collect(preg_split('/\s+/u', mb_strtoupper(trim($description)), -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($w) => preg_match('/\d/u', $w) || in_array($w, $generic, true) || mb_strlen($w) < 3)
            ->take(2);

        return mb_substr($words->implode(' ') ?: trim($description), 0, 100);
    }

    /**
     * The keyword must be in the description and, when the rule has one, the
     * amount (in or out) must be a whole multiple of `amount_multiple_cents`.
     */
    public function matches(string $description, ?int $amountCents = null): bool
    {
        if (mb_stripos($description, $this->pattern) === false) {
            return false;
        }

        if (filled($this->exclude_pattern) && mb_stripos($description, $this->exclude_pattern) !== false) {
            return false;
        }

        if ($this->amount_multiple_cents) {
            return $amountCents !== null && abs($amountCents) % $this->amount_multiple_cents === 0;
        }

        return true;
    }
}
