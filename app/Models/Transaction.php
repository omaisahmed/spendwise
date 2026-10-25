<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transaction extends Model
{
    public const TYPE_EXPENSE = 'expense';

    public const TYPE_INCOME = 'income';

    public const TYPE_TRANSFER = 'transfer';

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'debit_card' => 'Debit card',
        'credit_card' => 'Credit card',
        'bank_transfer' => 'Bank transfer',
        'mobile_wallet' => 'Mobile wallet',
        'other' => 'Other',
    ];

    protected $fillable = [
        'type', 'amount_minor', 'category_id', 'account_id', 'to_account_id', 'occurred_at',
        'payment_method', 'merchant', 'notes', 'is_refund', 'recurring_transaction_id', 'transfer_group',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'occurred_at' => 'datetime',
            'is_refund' => 'boolean',
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBetween(Builder $query, CarbonInterface $start, CarbonInterface $endExclusive): Builder
    {
        return $query->where('occurred_at', '>=', $start)->where('occurred_at', '<', $endExclusive);
    }

    public function scopeExpenses(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    public function scopeTransfers(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TRANSFER);
    }

    public function signedExpenseAmount(): int
    {
        return $this->is_refund ? -$this->amount_minor : $this->amount_minor;
    }

    public function displayTitle(): string
    {
        if ($this->merchant) {
            return $this->merchant;
        }

        if ($this->type === self::TYPE_TRANSFER) {
            return 'Transfer to '.($this->toAccount?->name ?? 'account');
        }

        return $this->category?->name ?? ucfirst($this->type);
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }
}
