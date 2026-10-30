<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return (new StoreCategoryRequest)->messages();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('company_id', $this->user()->company_id),
            ],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'revenue_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->user()->company_id)->where('type', 'revenue')],
            'cogs_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->user()->company_id)->where('type', 'expense')],
            'inventory_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->user()->company_id)->where('type', 'asset')],
        ];
    }
}
