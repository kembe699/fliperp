<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $product = $this->route('product');

        return [
            'category_id' => ['sometimes', 'required', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('company_id', $companyId)->ignore($product),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'unit_of_measure_id' => ['sometimes', 'required', Rule::exists('units_of_measure', 'id')->where('company_id', $companyId)],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'track_inventory' => ['nullable', 'boolean'],
        ];
    }
}
