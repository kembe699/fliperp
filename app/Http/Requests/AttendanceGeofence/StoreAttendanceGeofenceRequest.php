<?php

namespace App\Http\Requests\AttendanceGeofence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $this->user()->company_id)],
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
