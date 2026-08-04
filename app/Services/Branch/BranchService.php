<?php

namespace App\Services\Branch;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BranchService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Branch::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Branch
    {
        return Branch::create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch;
    }

    public function delete(Branch $branch): void
    {
        $branch->delete();
    }
}
