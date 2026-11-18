<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A marketing email sent to an audience of leads and/or customers (see Crm\EmailCampaignService). */
class CrmEmailCampaign extends TenantModel
{
    protected $fillable = [
        'company_id', 'name', 'subject', 'body', 'audience', 'status', 'scheduled_at', 'started_at',
        'finished_at', 'recipients_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CrmEmailCampaignRecipient::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
