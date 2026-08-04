<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Scopes\CompanyScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // User extends Authenticatable (not TenantModel) so PHP's single-inheritance
    // doesn't allow both; the same CompanyScope is applied manually here instead.
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (User $user) {
            if (! $user->company_id && Auth::check()) {
                $user->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}
