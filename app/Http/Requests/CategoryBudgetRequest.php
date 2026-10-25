<?php

namespace App\Http\Requests;

use App\Models\Budget;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $budget = $this->route('budget');
        $categoryBudgetId = $this->route('categoryBudget')?->id;

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('type', 'expense')->whereNull('archived_at')),
                Rule::unique('category_budgets')->where(fn ($query) => $query->where('budget_id', $budget instanceof Budget ? $budget->id : $budget))->ignore($categoryBudgetId),
            ],
            'amount' => ['required', 'string', 'regex:/^[+]?\d{1,15}(?:\.\d{1,2})?$/'],
            'warning_threshold' => ['nullable', 'integer', 'min:50', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty()) {
                    try {
                        if (Money::parse($this->input('amount')) <= 0) {
                            $validator->errors()->add('amount', 'The category limit must be greater than zero.');
                        }
                    } catch (\Throwable) {
                        $validator->errors()->add('amount', 'Enter a valid category limit.');
                    }
                }
            },
        ];
    }
}
