<?php

namespace App\Http\Requests\AssetMaintenanceLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'performed_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
