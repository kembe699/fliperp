<?php

namespace App\Http\Requests\RestaurantTable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['available', 'occupied', 'reserved'])],
        ];
    }
}
