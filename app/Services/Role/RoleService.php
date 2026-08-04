<?php

namespace App\Services\Role;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()->with('permissions')->latest()->paginate($perPage);
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
        $role->delete();
    }
}
