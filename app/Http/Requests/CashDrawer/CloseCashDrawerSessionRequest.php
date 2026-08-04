<?php

namespace App\Http\Requests\CashDrawer;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashDrawerSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_float' => ['required', 'numeric', 'min:0'],
        ];
    }
}
