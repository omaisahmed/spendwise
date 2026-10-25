<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTransaction extends Model
{
    public const FREQUENCIES = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'type', 'amount_minor', 'category_id', 'account_id', 'to_account_id', 'frequency',
        'anchor_day', 'start_date', 'end_date', 'next_due_date', 'merchant', 'notes',
        'payment_method', 'reminder_enabled', 'status', 'last_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'anchor_day' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due_date' => 'date',
            'reminder_enabled' => 'boolean',
            'last_processed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringOccurrence::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDue(Builder $query, ?string $date = null): Builder
    {
        return $query->active()->whereDate('next_due_date', '<=', $date ?? now()->toDateString());
    }
}
