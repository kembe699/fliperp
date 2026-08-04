<?php

namespace App\Http\Requests\EmployeeContract;

use Illuminate\Foundation\Http\FormRequest;

class SignEmployeeContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_data' => ['required', 'string', 'regex:/^data:image\/png;base64,/'],
            'signed_by_name' => ['required', 'string', 'max:255'],
        ];
    }
}
