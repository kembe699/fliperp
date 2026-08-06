<?php

namespace App\Services\Inventory;

use App\Models\StockAdjustment;
use App\Models\StockLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(protected StockMovementService $stockMovementService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return StockAdjustment::query()
            ->with('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            $adjustment = StockAdjustment::create([
                'warehouse_id' => $data['warehouse_id'],
                'reference_number' => $data['reference_number'],
                'reason' => $data['reason'] ?? null,
                'adjusted_by' => Auth::id(),
                'status' => 'draft',
            ]);

            foreach ($data['items'] as $item) {
                $systemQuantity = StockLevel::where('product_id', $item['product_id'])
                    ->where('product_variant_id', $item['product_variant_id'] ?? null)
                    ->where('warehouse_id', $adjustment->warehouse_id)
                    ->value('quantity_on_hand') ?? 0;

                $adjustment->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'system_quantity' => $systemQuantity,
                    'counted_quantity' => $item['counted_quantity'],
                ]);
            }

            return $adjustment->load('items');
        });
    }

    public function update(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        if ($adjustment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft adjustments can be edited.'],
            ]);
        }

        return DB::transaction(function () use ($adjustment, $data) {
            $adjustment->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $adjustment->items()->delete();

                foreach ($data['items'] as $item) {
                    $systemQuantity = StockLevel::where('product_id', $item['product_id'])
                        ->where('product_variant_id', $item['product_variant_id'] ?? null)
                        ->where('warehouse_id', $adjustment->warehouse_id)
                        ->value('quantity_on_hand') ?? 0;

                    $adjustment->items()->create([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'system_quantity' => $systemQuantity,
                        'counted_quantity' => $item['counted_quantity'],
                    ]);
                }
            }

            return $adjustment->fresh('items');
        });
    }

    public function delete(StockAdjustment $adjustment): void
    {
        if ($adjustment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft adjustments can be deleted.'],
            ]);
        }

        $adjustment->delete();
    }

    public function approve(StockAdjustment $adjustment): StockAdjustment
    {
        if ($adjustment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft adjustments can be approved.'],
            ]);
        }

        return DB::transaction(function () use ($adjustment) {
            $adjustment = StockAdjustment::query()->lockForUpdate()->with('items')->findOrFail($adjustment->id);

            // Re-check after the lock: guards against two concurrent approve
            // requests both passing the pre-transaction check and each
            // applying every item's variance a second time.
            if ($adjustment->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['Only draft adjustments can be approved.'],
                ]);
            }

            foreach ($adjustment->items as $item) {
                $variance = (float) $item->variance;

                if ($variance === 0.0) {
                    continue;
                }

                $this->stockMovementService->record([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'movement_type' => 'adjustment',
                    'quantity' => $variance,
                    'reference_type' => StockAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'reason' => $adjustment->reason,
                    // The physical count is ground truth: system_quantity was
                    // captured when this item was created, so applying
                    // variance here always lands exactly on counted_quantity.
                    'allow_negative_stock' => true,
                ]);
            }

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);

            return $adjustment->fresh('items');
        });
    }
}
