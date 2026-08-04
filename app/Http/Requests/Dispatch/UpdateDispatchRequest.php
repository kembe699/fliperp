<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $dispatch = $this->route('dispatch');

        return [
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('dispatches', 'reference_number')->where('company_id', $companyId)->ignore($dispatch),
            ],
            'source_module' => ['sometimes', 'required', Rule::in(['procurement', 'sales', 'transfer'])],
            'source_id' => ['nullable', 'integer'],
            'dispatch_date' => ['sometimes', 'required', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'in_transit', 'delivered', 'cancelled'])],
            'items' => ['nullable', 'array'],
            'items.*.item_description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
        ];
    }
}
