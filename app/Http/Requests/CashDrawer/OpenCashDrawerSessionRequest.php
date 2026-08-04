<?php

namespace App\Http\Requests\CashDrawer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashDrawerSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ];
    }
}
