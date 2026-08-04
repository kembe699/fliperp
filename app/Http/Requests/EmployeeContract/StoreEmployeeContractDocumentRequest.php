<?php

namespace App\Http\Requests\EmployeeContract;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeContractDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'is_signed_physical_copy' => ['nullable', 'boolean'],
            'signed_by_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
