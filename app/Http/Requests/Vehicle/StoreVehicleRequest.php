<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
            'registration_number' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'registration_number')->where('company_id', $companyId)],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['available', 'in_transit', 'maintenance'])],
        ];
    }
}
