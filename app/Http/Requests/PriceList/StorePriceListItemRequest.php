<?php

namespace App\Http\Requests\PriceList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
