<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $variant = $this->route('variant');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('product_variants', 'sku')->where('product_id', $product?->id)->ignore($variant),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'price_adjustment' => ['nullable', 'numeric'],
        ];
    }
}
