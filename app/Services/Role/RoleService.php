<?php

namespace App\Services\Role;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
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
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Role
    {
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function update(Role $role, array $data): Role
    {
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
}
