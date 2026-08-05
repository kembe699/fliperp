<?php

namespace App\Http\Requests\CrmMeeting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'lead_id' => ['nullable', Rule::exists('crm_leads', 'id')->where('company_id', $companyId)],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where('company_id', $companyId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'string', 'max:255'],
            'organizer_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'attendees' => ['nullable', 'array'],
            'attendees.*.user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'attendees.*.external_name' => ['nullable', 'string', 'max:255'],
            'attendees.*.external_email' => ['nullable', 'email'],
        ];
    }
}
