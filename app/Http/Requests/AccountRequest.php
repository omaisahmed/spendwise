<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(Account::TYPES))],
            'opening_balance' => ['required', 'string', 'regex:/^[+-]?\d{1,15}(?:\.\d{1,2})?$/'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()) {
                    try {
                        Money::parse($this->input('opening_balance'));
                    } catch (\Throwable) {
                        $validator->errors()->add('opening_balance', 'Enter a valid opening balance.');
                    }
                }
            },
        ];
    }
}
