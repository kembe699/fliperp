<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEmailCampaignRecipient extends TenantModel
{
    protected $fillable = [
        'company_id', 'campaign_id', 'lead_id', 'customer_id', 'email', 'name', 'token', 'status',
        'error_message', 'sent_at', 'opened_at', 'open_count', 'clicked_at', 'click_count', 'unsubscribed_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'open_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CrmEmailCampaign::class, 'campaign_id');
    }
}
