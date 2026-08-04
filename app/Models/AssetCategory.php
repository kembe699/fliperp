<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'depreciation_method',
        'useful_life_years',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
