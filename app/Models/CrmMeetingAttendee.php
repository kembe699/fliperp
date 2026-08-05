<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMeetingAttendee extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'external_name',
        'external_email',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CrmMeeting::class, 'meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
