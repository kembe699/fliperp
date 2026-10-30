<?php

namespace App\Http\Requests\Platform;

use App\Support\Modules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'enabled_modules' => ['sometimes', 'array'],
            'enabled_modules.*' => ['string', Rule::in(Modules::keys())],
        ];
    }
}
