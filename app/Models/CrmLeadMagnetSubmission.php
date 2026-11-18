<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLeadMagnetSubmission extends TenantModel
{
    protected $fillable = [
        'company_id', 'lead_magnet_id', 'lead_id', 'name', 'email', 'phone', 'company_name',
        'download_token', 'download_count', 'downloaded_at',
    ];

    protected $hidden = ['download_token'];

    protected function casts(): array
    {
        return ['downloaded_at' => 'datetime', 'download_count' => 'integer'];
    }

    public function leadMagnet(): BelongsTo
    {
        return $this->belongsTo(CrmLeadMagnet::class, 'lead_magnet_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }
}
