<?php

namespace App\Http\Requests\RestaurantTable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['available', 'occupied', 'reserved'])],
        ];
    }
}
