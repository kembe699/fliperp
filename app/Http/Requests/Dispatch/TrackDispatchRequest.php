<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;

class TrackDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
