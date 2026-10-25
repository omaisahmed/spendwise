<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const TYPE_EXPENSE = 'expense';

    public const TYPE_INCOME = 'income';

    protected $fillable = [
        'name', 'type', 'icon', 'color', 'is_essential', 'sort_order', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_essential' => 'boolean',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function categoryBudgets(): HasMany
    {
        return $this->hasMany(CategoryBudget::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public static function iconChoices(): array
    {
        return [
            'utensils', 'coffee', 'shopping-basket', 'shopping-bag', 'bus', 'car', 'fuel', 'plane',
            'house', 'receipt', 'heart-pulse', 'graduation-cap', 'clapperboard', 'sparkles', 'users',
            'repeat', 'gift', 'baby', 'paw-print', 'dumbbell', 'wifi', 'phone', 'zap', 'droplets',
            'banknote', 'briefcase', 'laptop', 'trending-up', 'building', 'circle-ellipsis', 'circle',
        ];
    }

    public static function colorChoices(): array
    {
        return [
            '#635BFF', '#8B7CFF', '#4E46E5', '#16A878', '#F05B6B', '#F2AE43', '#4395F7',
            '#EC4899', '#14B8A6', '#8B5CF6', '#64748B', '#EF4444',
        ];
    }
}
