<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A public page where clients book a meeting slot (see Crm\BookingService). */
class CrmBookingPage extends TenantModel
{
    protected $fillable = [
        'company_id', 'slug', 'title', 'description', 'duration_minutes', 'buffer_minutes', 'days',
        'start_time', 'end_time', 'timezone', 'min_notice_hours', 'max_days_ahead', 'host_user_id',
        'location', 'meeting_link', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'min_notice_hours' => 'integer',
            'max_days_ahead' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(CrmMeeting::class, 'booking_page_id');
    }
}
