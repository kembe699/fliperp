<?php

namespace App\Services\Hr;

use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PositionService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Position::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Position
    {
        return Position::create($data);
    }

    public function update(Position $position, array $data): Position
    {
        $position->update($data);

        return $position;
    }

    public function delete(Position $position): void
    {
        $position->delete();
    }
}
