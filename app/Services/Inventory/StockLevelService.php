<?php

namespace App\Services\Inventory;

use App\Models\StockLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StockLevelService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return StockLevel::query()
            ->with(['product', 'warehouse'])
            ->whereHas('product')
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $id) => $query->where('product_id', $id))
            ->when($filters['low_stock'] ?? null, fn (Builder $query) => $query->whereHas(
                'product',
                fn (Builder $productQuery) => $productQuery->whereColumn('stock_levels.quantity_on_hand', '<=', 'products.reorder_level')
            ))
            ->paginate($perPage);
    }
}
