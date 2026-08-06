<?php

namespace App\Services\Inventory;

use App\Models\StockTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(protected StockMovementService $stockMovementService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->with('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from_warehouse_id'] ?? null, fn ($query, $id) => $query->where('from_warehouse_id', $id))
            ->when($filters['to_warehouse_id'] ?? null, fn ($query, $id) => $query->where('to_warehouse_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'reference_number' => $data['reference_number'],
                'status' => 'pending',
                'initiated_by' => Auth::id(),
            ]);

            $transfer->items()->createMany($data['items']);

            return $transfer->load('items');
        });
    }

    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending transfers can be edited.'],
            ]);
        }

        return DB::transaction(function () use ($transfer, $data) {
            $transfer->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $transfer->items()->delete();
                $transfer->items()->createMany($data['items']);
            }

            return $transfer->fresh('items');
        });
    }

    public function delete(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending transfers can be deleted.'],
            ]);
        }

        $transfer->delete();
    }

    public function markInTransit(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending transfers can be marked in transit.'],
            ]);
        }

        $transfer->update(['status' => 'in_transit']);

        return $transfer;
    }

    public function complete(StockTransfer $transfer, bool $allowNegativeStock = false): StockTransfer
    {
        if (! in_array($transfer->status, ['pending', 'in_transit'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only pending or in-transit transfers can be completed.'],
            ]);
        }

        return DB::transaction(function () use ($transfer, $allowNegativeStock) {
            $transfer = StockTransfer::query()->lockForUpdate()->with('items')->findOrFail($transfer->id);

            // Re-check after the lock: guards against two concurrent complete
            // requests both passing the pre-transaction check and each
            // moving every item's quantity a second time.
            if (! in_array($transfer->status, ['pending', 'in_transit'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending or in-transit transfers can be completed.'],
                ]);
            }

            foreach ($transfer->items as $item) {
                $this->stockMovementService->record([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'movement_type' => 'transfer_out',
                    'quantity' => -abs((float) $item->quantity),
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'allow_negative_stock' => $allowNegativeStock,
                ]);

                $this->stockMovementService->record([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'movement_type' => 'transfer_in',
                    'quantity' => abs((float) $item->quantity),
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                ]);
            }

            $transfer->update(['status' => 'completed']);

            return $transfer->fresh('items');
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => ['A completed transfer cannot be cancelled.'],
            ]);
        }

        $transfer->update(['status' => 'cancelled']);

        return $transfer;
    }
}
