<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrence extends Model
{
    protected $fillable = [
        'recurring_transaction_id', 'due_date', 'status', 'transaction_id', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
