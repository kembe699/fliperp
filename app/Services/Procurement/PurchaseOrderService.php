<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrder;
use App\Notifications\PurchaseOrderApprovalNeeded;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(protected NotificationRecipientResolver $recipientResolver) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->latest('order_date')
            ->paginate($perPage);
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create([
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'reference_number' => $data['reference_number'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $purchaseOrder->items()->createMany($data['items']);

            return $purchaseOrder->load('items');
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft purchase orders can be edited. Create a new purchase order instead.'],
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $purchaseOrder->items()->delete();
                $purchaseOrder->items()->createMany($data['items']);
            }

            return $purchaseOrder->fresh('items');
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft purchase orders can be deleted.'],
            ]);
        }

        if ($purchaseOrder->goodsReceivedNotes()->exists() || $purchaseOrder->bills()->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => ['Cannot delete a purchase order that has GRNs or bills.'],
            ]);
        }

        $purchaseOrder->delete();
    }

    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft purchase orders can be submitted.'],
            ]);
        }

        $purchaseOrder->update(['status' => 'submitted']);

        $approvers = $this->recipientResolver->usersWithPermission(
            $purchaseOrder->company_id,
            'purchase-orders.approve',
            $purchaseOrder->branch_id,
        )->reject(fn ($user) => $user->id === Auth::id());
        Notification::send($approvers, new PurchaseOrderApprovalNeeded($purchaseOrder));

        return $purchaseOrder;
    }

    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => ['Only submitted purchase orders can be approved.'],
            ]);
        }

        $purchaseOrder->update(['status' => 'approved', 'approved_by' => Auth::id()]);

        return $purchaseOrder;
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! in_array($purchaseOrder->status, ['draft', 'submitted', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft, submitted or approved purchase orders can be cancelled.'],
            ]);
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return $purchaseOrder;
    }

    public function receivingStatus(PurchaseOrder $purchaseOrder): array
    {
        return [
            'purchase_order_id' => $purchaseOrder->id,
            'status' => $purchaseOrder->status,
            'items' => $purchaseOrder->items->map(fn ($item) => [
                'purchase_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity_ordered' => (float) $item->quantity_ordered,
                'quantity_received' => (float) $item->quantity_received,
                'quantity_outstanding' => round((float) $item->quantity_ordered - (float) $item->quantity_received, 2),
                'fully_received' => (float) $item->quantity_received >= (float) $item->quantity_ordered,
            ])->all(),
        ];
    }
}
