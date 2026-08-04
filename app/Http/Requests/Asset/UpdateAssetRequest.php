<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $asset = $this->route('asset');

        return [
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'asset_category_id' => ['sometimes', 'required', Rule::exists('asset_categories', 'id')->where('company_id', $companyId)],
            'asset_code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('assets', 'asset_code')->where('company_id', $companyId)->ignore($asset),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'purchase_date' => ['sometimes', 'required', 'date'],
            'purchase_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['in_use', 'in_storage', 'under_maintenance', 'disposed'])],
            'assigned_to_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
        ];
    }
}
