<?php

namespace App\Http\Requests\CostCentre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $costCentre = $this->route('cost_centre');
        $required = $costCentre ? ['sometimes', 'required'] : ['required'];

        return [
            'code' => [...$required, 'string', 'max:50', Rule::unique('cost_centres', 'code')->where('company_id', $this->user()->company_id)->ignore($costCentre)],
            'name' => [...$required, 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
