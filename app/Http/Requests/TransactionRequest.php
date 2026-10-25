<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_refund' => $this->boolean('is_refund'),
            'type' => $this->input('type', Transaction::TYPE_EXPENSE),
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
            'occurred_at' => ['required', 'date_format:Y-m-d'],
            'occurred_time' => ['nullable', 'date_format:H:i'],
            'payment_method' => ['required', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],
            'merchant' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_refund' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    if (Money::parse($this->input('amount')) <= 0) {
                        $validator->errors()->add('amount', 'The amount must be greater than zero.');
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('amount', 'Enter a valid amount.');
                }

                if ($this->input('type') === Transaction::TYPE_TRANSFER && $this->boolean('is_refund')) {
                    $validator->errors()->add('is_refund', 'A transfer cannot be marked as a refund.');
                }

                if ($this->input('type') !== Transaction::TYPE_TRANSFER && $this->filled('to_account_id')) {
                    $validator->errors()->add('to_account_id', 'Only transfers can have a destination account.');
                }

                if ($this->filled('category_id')) {
                    $category = Category::query()->find($this->integer('category_id'));
                    if ($category && $category->type !== $this->input('type')) {
                        $validator->errors()->add('category_id', 'Choose a category that matches the transaction type.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'to_account_id.different' => 'The destination account must be different from the source account.',
            'category_id.required' => 'Choose a category for this transaction.',
        ];
    }
}
