<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['percentage_discount', 'fixed_discount', 'buy_x_get_y'])],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'applies_to' => ['sometimes', 'required', Rule::in(['all_products', 'category', 'specific_products'])],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [Rule::exists('products', 'id')->where('company_id', $companyId)],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
