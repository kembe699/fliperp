<?php

namespace App\Http\Requests\Donor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $donor = $this->route('donor');
        $required = $donor ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:255', Rule::unique('donors', 'name')->where('company_id', $this->user()->company_id)->ignore($donor)],
            'code' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
