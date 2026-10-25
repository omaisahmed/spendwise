<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notifications_enabled' => $this->boolean('notifications_enabled'),
            'privacy_mode' => $this->boolean('privacy_mode'),
        ]);
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:80'],
            'currency' => ['required', Rule::in(array_keys(Money::SYMBOLS))],
            'month_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'monthly_income' => ['nullable', 'string', 'regex:/^[+]?\d{1,15}(?:\.\d{1,2})?$/'],
            'budget_warning_threshold' => ['required', 'integer', 'min:50', 'max:100'],
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'notifications_enabled' => ['boolean'],
            'privacy_mode' => ['boolean'],
            'date_format' => ['required', Rule::in(['d M Y', 'd/m/Y', 'Y-m-d', 'M d, Y'])],
        ];
    }
}
