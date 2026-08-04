<?php

namespace App\Services\Hr;

use App\Models\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Attendance::query()
            ->whereHas('employee')
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                $filters['department_id'] ?? null,
                fn ($query, $id) => $query->whereHas('employee', fn ($employees) => $employees->where('department_id', $id)),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas(
                'employee',
                fn ($employees) => $employees
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhereRaw("first_name || ' ' || last_name like ?", ["%{$search}%"]),
            ))
            ->latest('date')
            ->paginate($perPage);
    }

    public function create(array $data): Attendance
    {
        return Attendance::create($data);
    }

    public function update(Attendance $attendance, array $data): Attendance
    {
        $attendance->update($data);

        return $attendance;
    }

    public function delete(Attendance $attendance): void
    {
        $attendance->delete();
    }
}
