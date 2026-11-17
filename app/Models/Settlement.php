<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['from_user_id', 'to_user_id', 'amount_cents', 'date', 'note'])]
class Settlement extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'amount_cents' => 'integer'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
