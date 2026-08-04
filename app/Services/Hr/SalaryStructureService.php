<?php

namespace App\Services\Hr;

use App\Models\SalaryStructure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalaryStructureService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return SalaryStructure::query()
            ->whereHas('employee')
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->latest('effective_date')
            ->paginate($perPage);
    }

    public function create(array $data): SalaryStructure
    {
        return SalaryStructure::create($data);
    }

    public function update(SalaryStructure $salaryStructure, array $data): SalaryStructure
    {
        $salaryStructure->update($data);

        return $salaryStructure;
    }

    public function delete(SalaryStructure $salaryStructure): void
    {
        $salaryStructure->delete();
    }
}
