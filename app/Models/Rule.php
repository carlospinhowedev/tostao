<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pattern', 'category_id', 'owner_id', 'set_owner', 'priority'])]
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

    public function matches(string $description): bool
    {
        return mb_stripos($description, $this->pattern) !== false;
    }
}
