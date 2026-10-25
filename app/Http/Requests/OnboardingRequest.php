<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:80'],
            'starting_balance' => ['required', 'string', 'regex:/^[+-]?\d{1,15}(?:\.\d{1,2})?$/'],
            'monthly_income' => ['nullable', 'string', 'regex:/^[+]?\d{1,15}(?:\.\d{1,2})?$/'],
            'currency' => ['required', Rule::in(array_keys(Money::SYMBOLS))],
            'month_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'account_name' => ['required', 'string', 'max:80'],
            'account_type' => ['required', Rule::in(['cash', 'bank', 'wallet', 'savings'])],
        ];
    }
}
