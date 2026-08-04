<?php

namespace App\Services\Inventory;

use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Warehouse::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse;
    }

    public function delete(Warehouse $warehouse): void
    {
        $hasStock = StockLevel::where('warehouse_id', $warehouse->id)->where('quantity_on_hand', '!=', 0)->exists();

        if ($hasStock) {
            throw ValidationException::withMessages([
                'warehouse' => ['Cannot delete a warehouse that still holds stock.'],
            ]);
        }

        $warehouse->delete();
    }
}
