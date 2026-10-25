<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public const TYPES = [
        'cash' => 'Cash',
        'bank' => 'Bank account',
        'wallet' => 'Mobile wallet',
        'savings' => 'Savings account',
        'custom' => 'Custom account',
    ];

    protected $fillable = [
        'name', 'type', 'icon', 'color', 'opening_balance_minor', 'is_default', 'is_archived', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_minor' => 'integer',
            'is_default' => 'boolean',
            'is_archived' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'bank' => 'landmark',
            'wallet' => 'smartphone',
            'savings' => 'piggy-bank',
            'cash' => 'wallet',
            default => 'credit-card',
        };
    }
}
