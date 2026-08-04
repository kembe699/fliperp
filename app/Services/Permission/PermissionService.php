<?php

namespace App\Services\Permission;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Permission::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Permission
    {
        return Permission::create(['name' => $data['name'], 'guard_name' => 'web']);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update(['name' => $data['name']]);

        return $permission;
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
    }
}
