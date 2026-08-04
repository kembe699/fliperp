<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDispatchRequest extends FormRequest
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
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'reference_number' => ['required', 'string', 'max:255', Rule::unique('dispatches', 'reference_number')->where('company_id', $companyId)],
            'source_module' => ['required', Rule::in(['procurement', 'sales', 'transfer'])],
            'source_id' => ['nullable', 'integer'],
            'dispatch_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'in_transit', 'delivered', 'cancelled'])],
            'items' => ['nullable', 'array'],
            'items.*.item_description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
        ];
    }
}
