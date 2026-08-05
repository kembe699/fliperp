<?php

namespace App\Http\Requests\CrmMeeting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'string', 'max:255'],
            'organizer_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ];
    }
}
