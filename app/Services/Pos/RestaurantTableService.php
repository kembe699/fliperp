<?php

namespace App\Services\Pos;

use App\Models\RestaurantTable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RestaurantTableService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return RestaurantTable::query()->latest()->paginate($perPage);
    }

    public function create(array $data): RestaurantTable
    {
        return RestaurantTable::create($data);
    }

    public function update(RestaurantTable $table, array $data): RestaurantTable
    {
        $table->update($data);

        return $table;
    }

    public function delete(RestaurantTable $table): void
    {
        if ($table->status === 'occupied') {
            throw ValidationException::withMessages([
                'table' => ['Cannot delete a table that is currently occupied.'],
            ]);
        }

        $table->delete();
    }
}
