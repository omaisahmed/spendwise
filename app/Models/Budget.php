<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $fillable = [
        'name', 'month', 'amount_minor', 'warning_threshold', 'notes', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'warning_threshold' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function categoryBudgets(): HasMany
    {
        return $this->hasMany(CategoryBudget::class);
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        return $query->where('month', $month)->whereNull('archived_at');
    }
}
