<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends TenantModel
{
    use Auditable;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'company_id',
        'name',
        'abbreviation',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
