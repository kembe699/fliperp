<?php

namespace App\Http\Requests\AssetCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'depreciation_method' => ['required', Rule::in(['straight_line', 'reducing_balance'])],
            'useful_life_years' => ['required', 'integer', 'min:1'],
        ];
    }
}
