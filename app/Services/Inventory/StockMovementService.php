<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single choke point for every inventory-affecting write. Every other
 * inventory service (transfers, adjustments, the manual correction endpoint)
 * must move stock through record()/manual() so stock_levels can never drift
 * from the stock_movements history.
 */
class StockMovementService
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function record(array $data): StockMovement
    {
        return DB::transaction(fn () => $this->applyMovement($data)['movement']);
    }

    public function manual(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $result = $this->applyMovement($data + [
                'movement_type' => 'adjustment',
                'reference_type' => 'manual',
            ]);

            $this->auditLogService->record(
                'manual_stock_adjustment',
                $result['movement'],
                ['quantity_on_hand' => $result['before']],
                ['quantity_on_hand' => $result['after']],
            );

            return $result['movement'];
        });
    }

    protected function applyMovement(array $data): array
    {
        $product = Product::findOrFail($data['product_id']);

        $stockLevel = $this->lockOrCreateStockLevel(
            $data['product_id'],
            $data['product_variant_id'] ?? null,
            $data['warehouse_id'],
        );

        $before = (float) $stockLevel->quantity_on_hand;
        $quantity = (float) $data['quantity'];
        $after = $before + $quantity;

        $allowNegative = ! $product->track_inventory || ($data['allow_negative_stock'] ?? false);

        if ($after < 0 && ! $allowNegative) {
            throw ValidationException::withMessages([
                'quantity' => ['This movement would take quantity_on_hand below zero.'],
            ]);
        }

        $stockLevel->update(['quantity_on_hand' => $after]);

        $movement = StockMovement::create([
            'product_id' => $data['product_id'],
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'],
            'movement_type' => $data['movement_type'],
            'quantity' => $quantity,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reason' => $data['reason'] ?? null,
            'performed_by' => Auth::id(),
            'moved_at' => $data['moved_at'] ?? now(),
        ]);

        return ['movement' => $movement, 'before' => $before, 'after' => $after];
    }

    protected function lockOrCreateStockLevel(int $productId, ?int $productVariantId, int $warehouseId): StockLevel
    {
        $stockLevel = StockLevel::where('product_id', $productId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        return $stockLevel ?? StockLevel::create([
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);
    }
}
