<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A download (file or link) offered on a public page in exchange for contact details. */
class CrmLeadMagnet extends TenantModel
{
    protected $fillable = [
        'company_id', 'slug', 'title', 'headline', 'description', 'file_path', 'file_name', 'external_url',
        'button_label', 'thank_you_message', 'ask_phone', 'ask_company', 'is_active', 'views_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ask_phone' => 'boolean',
            'ask_company' => 'boolean',
            'is_active' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CrmLeadMagnetSubmission::class, 'lead_magnet_id');
    }
}
