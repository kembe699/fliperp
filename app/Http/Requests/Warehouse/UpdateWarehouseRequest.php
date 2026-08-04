<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $warehouse = $this->route('warehouse');

        return [
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('warehouses', 'code')->where('company_id', $companyId)->ignore($warehouse),
            ],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
