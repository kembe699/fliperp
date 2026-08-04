<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percentage_discount', 'fixed_discount', 'buy_x_get_y'])],
            'value' => ['required', 'numeric', 'min:0'],
            'applies_to' => ['required', Rule::in(['all_products', 'category', 'specific_products'])],
            'category_id' => ['nullable', 'required_if:applies_to,category', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'product_ids' => ['nullable', 'required_if:applies_to,specific_products', 'array'],
            'product_ids.*' => [Rule::exists('products', 'id')->where('company_id', $companyId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
