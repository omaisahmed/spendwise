<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavingsContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^[+-]?\d{1,15}(?:\.\d{1,2})?$/'],
            'occurred_at' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:200'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('is_archived', false))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()) {
                    try {
                        if (Money::parse($this->input('amount')) === 0) {
                            $validator->errors()->add('amount', 'Enter an amount to add or withdraw.');
                        }
                    } catch (\Throwable) {
                        $validator->errors()->add('amount', 'Enter a valid amount.');
                    }
                }
            },
        ];
    }
}
