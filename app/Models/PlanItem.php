<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'kind', 'amount_cents', 'owner_id', 'nature', 'group_name', 'category_id', 'months', 'active'])]
class PlanItem extends Model
{
    public const KINDS = ['income', 'expense', 'saving'];

    /** How predictable an expense is: green, amber and plain in the plan. */
    public const NATURES = ['fixed', 'variable', 'optional'];

    public const NATURE_LABELS = ['fixed' => 'Fixed', 'variable' => 'Variable but required', 'optional' => 'Optional'];

    protected function casts(): array
    {
        return ['months' => 'array', 'active' => 'boolean', 'amount_cents' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Whether the item counts in a month (1 to 12). No months listed means every month. */
    public function appliesTo(int $month): bool
    {
        return empty($this->months) || in_array($month, array_map('intval', $this->months), true);
    }
}
