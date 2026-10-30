<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Scopes\CompanyScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use HasRoles {
        hasPermissionTo as protected roleHasPermissionTo;
        getAllPermissions as protected roleGetAllPermissions;
    }

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'is_platform_staff',
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
            'is_platform_staff' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * A permission in a module switched off for the user's company is denied,
     * whatever their roles say (see App\Support\Modules). Every policy,
     * `permission:` route middleware and Gate check comes through here.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;
        if (is_string($name) && $this->inDisabledModule($name)) {
            return false;
        }

        return $this->roleHasPermissionTo($permission, $guardName);
    }

    /** What the user can do: their roles' permissions, less those in modules switched off for their company. */
    public function getAllPermissions(): Collection
    {
        $disabled = $this->company?->disabledPermissionGroups() ?? [];

        return $disabled === []
            ? $this->roleGetAllPermissions()
            : $this->roleGetAllPermissions()->reject(fn ($permission) => in_array(Str::before($permission->name, '.'), $disabled, true))->values();
    }

    protected function inDisabledModule(string $permission): bool
    {
        $disabled = $this->company?->disabledPermissionGroups() ?? [];

        return $disabled !== [] && in_array(Str::before($permission, '.'), $disabled, true);
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

    public function assignedCrmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'assigned_to');
    }

    public function assignedCrmDeals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'assigned_to');
    }

    public function crmAccountAssignments(): HasMany
    {
        return $this->hasMany(CrmAccountAssignment::class, 'user_id');
    }

    public function loggedCrmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'logged_by');
    }

    /**
     * Controls the channel name Laravel's notification broadcasting uses
     * (see BroadcastNotificationCreated::channelName()) — without this it
     * defaults to "App.Models.User.{id}", which doesn't match the
     * private-user.{id} channel routes/channels.php actually authorizes.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.'.$this->id;
    }
}
