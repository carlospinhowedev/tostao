<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind', 'color', 'monthly_budget_cents'])]
class Category extends Model
{
    public const KINDS = ['expense', 'income', 'transfer'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
