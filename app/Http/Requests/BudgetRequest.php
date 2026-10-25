<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'string', 'regex:/^[+]?\d{1,15}(?:\.\d{1,2})?$/'],
            'warning_threshold' => ['required', 'integer', 'min:50', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()) {
                    try {
                        if (Money::parse($this->input('amount')) <= 0) {
                            $validator->errors()->add('amount', 'The budget must be greater than zero.');
                        }
                    } catch (\Throwable) {
                        $validator->errors()->add('amount', 'Enter a valid budget amount.');
                    }
                }
            },
        ];
    }
}
