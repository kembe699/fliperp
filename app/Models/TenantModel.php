<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model) {
            if (! $model->getAttribute('company_id') && Auth::check()) {
                $model->setAttribute('company_id', Auth::user()->company_id);
            }
        });
    }
}
