<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryBudget extends Model
{
    protected $fillable = ['budget_id', 'category_id', 'amount_minor', 'warning_threshold'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'warning_threshold' => 'integer',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
