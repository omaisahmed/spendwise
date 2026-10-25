<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_essential' => $this->boolean('is_essential'),
        ]);
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('categories')->where(fn ($query) => $query->where('type', $this->input('type')))->ignore($categoryId)],
            'type' => ['required', Rule::in([Category::TYPE_EXPENSE, Category::TYPE_INCOME])],
            'icon' => ['required', Rule::in(Category::iconChoices())],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_essential' => ['boolean'],
        ];
    }
}
