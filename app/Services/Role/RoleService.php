<?php

namespace App\Services\Role;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RoleService
{
    /**
     * A super_admin (no company_id of their own — a platform-level account,
     * not scoped to any single company) manages every role. A company_admin
     * only ever sees/touches the fixed system roles (company_id null) plus
     * their own company's custom roles — never another company's.
     */
    protected function isPlatformSuperAdmin(): bool
    {
        return Auth::user()?->company_id === null;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $user = Auth::user();

        // Deliberately not withCount('users'): that triggers Spatie's users()
        // relation on a fresh, unsaved Role instance, whose guard_name falls
        // back to config('auth.defaults.guard') — which Laravel's own
        // Authenticate middleware flips to 'sanctum' for the rest of any
        // auth:sanctum-authenticated request. Since 'sanctum' has no provider
        // mapping in config/auth.php, that resolution returns null and crashes.
        // A raw subquery against model_has_roles sidesteps guard resolution
        // entirely.
        return Role::query()
            ->with('permissions')
            ->selectRaw('roles.*, (select count(*) from model_has_roles where model_has_roles.role_id = roles.id and model_has_roles.model_type = ?) as users_count', [User::class])
            ->when(
                ! $this->isPlatformSuperAdmin(),
                fn ($query) => $query->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $user->company_id)),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function find(Role $role): Role
    {
        if (! $this->isPlatformSuperAdmin() && ! $role->isSystemRole() && $role->company_id !== Auth::user()->company_id) {
            throw ValidationException::withMessages([
                'role' => ['This role does not belong to your company.'],
            ]);
        }

        return $role->load('permissions');
    }

    public function create(array $data): Role
    {
        $user = Auth::user();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            // A super_admin creating a role with no company scope makes
            // another system-wide role; a company_admin's new role is
            // always scoped to their own company, never global.
            'company_id' => $this->isPlatformSuperAdmin() ? null : $user->company_id,
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function update(Role $role, array $data): Role
    {
        $this->assertMutable($role);

        if (! empty($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data) && $data['permissions'] !== null) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function delete(Role $role): void
    {
        $this->assertMutable($role);

        // model_has_roles.role_id cascades on delete, so without this check
        // deleting a role silently strips it from every user who has it —
        // no error, no warning, just gone.
        $userCount = $role->users()->count();

        if ($userCount > 0) {
            $label = $userCount === 1 ? '1 user' : "{$userCount} users";

            throw ValidationException::withMessages([
                'role' => ["Cannot delete this role: {$label} currently hold it. Reassign them to a different role first."],
            ]);
        }

        $role->delete();
    }

    /**
     * Segregation of tenants: a company_admin can never rename, re-permission,
     * or delete a system role (shared with every other company) or a custom
     * role belonging to a different company. Only a platform super_admin can
     * touch a system role.
     */
    protected function assertMutable(Role $role): void
    {
        if ($this->isPlatformSuperAdmin()) {
            return;
        }

        $user = Auth::user();

        if ($role->isSystemRole() || $role->company_id !== $user->company_id) {
            throw ValidationException::withMessages([
                'role' => ['This role does not belong to your company and cannot be modified.'],
            ]);
        }
    }
}
