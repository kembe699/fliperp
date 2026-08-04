<?php

namespace App\Http\Requests\PriceList;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3'],
            'is_default' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
