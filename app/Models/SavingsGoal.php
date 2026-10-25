<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsGoal extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'name', 'icon', 'color', 'target_minor', 'target_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_minor' => 'integer',
            'target_date' => 'date',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SavingsContribution::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function savedMinor(): int
    {
        return (int) $this->contributions()->sum('amount_minor');
    }
}
