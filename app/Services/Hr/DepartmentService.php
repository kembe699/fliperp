<?php

namespace App\Services\Hr;

use App\Models\Department;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

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
        if (Product::where('department_id', $department->id)->exists()) {
            throw ValidationException::withMessages([
                'department' => ['Cannot delete a department that still has products or services. Move them to another department first.'],
            ]);
        }

        $department->delete();
    }
}
