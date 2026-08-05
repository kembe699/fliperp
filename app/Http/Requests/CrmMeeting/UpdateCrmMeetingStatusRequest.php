<?php

namespace App\Http\Requests\CrmMeeting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmMeetingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
        ];
    }
}
