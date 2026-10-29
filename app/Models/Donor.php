<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'contact_email',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }
}
