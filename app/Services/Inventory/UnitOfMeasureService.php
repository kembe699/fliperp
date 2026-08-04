<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UnitOfMeasureService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return UnitOfMeasure::query()->latest()->paginate($perPage);
    }

    public function create(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create($data);
    }

    public function update(UnitOfMeasure $unitOfMeasure, array $data): UnitOfMeasure
    {
        $unitOfMeasure->update($data);

        return $unitOfMeasure;
    }

    public function delete(UnitOfMeasure $unitOfMeasure): void
    {
        if (Product::where('unit_of_measure_id', $unitOfMeasure->id)->exists()) {
            throw ValidationException::withMessages([
                'unit_of_measure' => ['Cannot delete a unit of measure that is still used by products.'],
            ]);
        }

        $unitOfMeasure->delete();
    }
}
