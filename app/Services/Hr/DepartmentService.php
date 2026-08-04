<?php

namespace App\Services\Hr;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepartmentService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Department::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Department
    {
        return Department::create($data);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department;
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }
}
