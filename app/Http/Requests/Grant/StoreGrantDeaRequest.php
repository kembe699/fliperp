<?php

namespace App\Http\Requests\Grant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrantDeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dea = $this->route('dea');
        $grantId = $dea?->grant_id ?? $this->route('grant')?->id;
        $required = $dea ? ['sometimes', 'required'] : ['required'];

        return [
            'code' => [...$required, 'string', 'max:50', Rule::unique('grant_deas', 'code')->where('grant_id', $grantId)->ignore($dea)],
            'name' => [...$required, 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'This SOF already has a DEA with this code.'];
    }
}
