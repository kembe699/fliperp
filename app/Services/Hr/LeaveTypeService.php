<?php

namespace App\Services\Hr;

use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeaveTypeService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return LeaveType::query()->latest()->paginate($perPage);
    }

    public function create(array $data): LeaveType
    {
        return LeaveType::create($data);
    }

    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        $leaveType->update($data);

        return $leaveType;
    }

    public function delete(LeaveType $leaveType): void
    {
        $leaveType->delete();
    }
}
