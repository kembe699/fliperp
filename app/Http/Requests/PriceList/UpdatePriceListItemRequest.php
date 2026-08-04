<?php

namespace App\Http\Requests\PriceList;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
