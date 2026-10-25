<?php

namespace App\Http\Requests;

use App\Models\SavingsGoal;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'target' => ['required', 'string', 'regex:/^[+]?\d{1,15}(?:\.\d{1,2})?$/'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in([SavingsGoal::STATUS_ACTIVE, SavingsGoal::STATUS_PAUSED, SavingsGoal::STATUS_COMPLETED])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()) {
                    try {
                        if (Money::parse($this->input('target')) <= 0) {
                            $validator->errors()->add('target', 'The target must be greater than zero.');
                        }
                    } catch (\Throwable) {
                        $validator->errors()->add('target', 'Enter a valid target amount.');
                    }
                }
            },
        ];
    }
}
