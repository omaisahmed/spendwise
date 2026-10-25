<?php

namespace App\Http\Requests;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecurringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reminder_enabled' => $this->boolean('reminder_enabled'),
        ]);
    }

    public function rules(): array
    {
        $isTransfer = $this->input('type') === Transaction::TYPE_TRANSFER;

        return [
            'type' => ['required', Rule::in([Transaction::TYPE_EXPENSE, Transaction::TYPE_INCOME, Transaction::TYPE_TRANSFER])],
            'amount' => ['required', 'string', 'regex:/^[+]?\d{1,15}(?:\.\d{1,2})?$/'],
            'category_id' => [$isTransfer ? 'nullable' : 'required', 'nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('type', $this->input('type'))->whereNull('archived_at'))],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('is_archived', false))],
            'to_account_id' => [$isTransfer ? 'required' : 'nullable', 'nullable', 'integer', 'different:account_id', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('is_archived', false))],
            'frequency' => ['required', Rule::in(array_keys(RecurringTransaction::FREQUENCIES))],
            'anchor_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'merchant' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],
            'reminder_enabled' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()) {
                    try {
                        if (Money::parse($this->input('amount')) <= 0) {
                            $validator->errors()->add('amount', 'The amount must be greater than zero.');
                        }
                    } catch (\Throwable) {
                        $validator->errors()->add('amount', 'Enter a valid amount.');
                    }
                }
            },
        ];
    }
}
