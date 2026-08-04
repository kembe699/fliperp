<?php

namespace App\Services\Procurement;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\Finance\JournalEntryService;
use App\Services\Inventory\StockMovementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The choke point for GRN confirmation: stock movements, PO receiving
 * progress, the auto-created supplier bill and its journal entry all happen
 * here, atomically, so they can never drift apart.
 */
class GrnService
{
    public const INVENTORY_ACCOUNT_CODE = '1200';

    public const ACCOUNTS_PAYABLE_ACCOUNT_CODE = '2000';

    public function __construct(
        protected StockMovementService $stockMovementService,
        protected JournalEntryService $journalEntryService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return GoodsReceivedNote::query()->with('items')->latest('received_date')->paginate($perPage);
    }

    public function create(array $data): GoodsReceivedNote
    {
        return DB::transaction(function () use ($data) {
            $grn = GoodsReceivedNote::create([
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'reference_number' => $data['reference_number'],
                'received_date' => $data['received_date'],
                'status' => 'draft',
                'received_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $grn->items()->createMany($this->normalizeItems($data['items']));

            return $grn->load('items');
        });
    }

    public function update(GoodsReceivedNote $grn, array $data): GoodsReceivedNote
    {
        if ($grn->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft GRNs can be edited.'],
            ]);
        }

        return DB::transaction(function () use ($grn, $data) {
            $grn->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $grn->items()->delete();
                $grn->items()->createMany($this->normalizeItems($data['items']));
            }

            return $grn->fresh('items');
        });
    }

    public function delete(GoodsReceivedNote $grn): void
    {
        if ($grn->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft GRNs can be deleted; a confirmed GRN has already posted stock and financial records.'],
            ]);
        }

        $grn->delete();
    }

    public function confirm(GoodsReceivedNote $grn): GoodsReceivedNote
    {
        if ($grn->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft GRNs can be confirmed.'],
            ]);
        }

        return DB::transaction(function () use ($grn) {
            $grn = GoodsReceivedNote::query()->lockForUpdate()->with('items')->findOrFail($grn->id);

            // Re-check after the lock: without it, a duplicate confirm
            // request (double-click, retry) would record stock movements,
            // update PO receiving progress, and auto-create a supplier
            // bill a second time instead of being rejected.
            if ($grn->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['Only draft GRNs can be confirmed.'],
                ]);
            }

            $goodSubtotal = 0.0;

            foreach ($grn->items as $item) {
                if ($item->condition === 'good') {
                    $this->stockMovementService->record([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $grn->warehouse_id,
                        'movement_type' => 'purchase',
                        'quantity' => (float) $item->quantity_received,
                        'reference_type' => GoodsReceivedNote::class,
                        'reference_id' => $grn->id,
                    ]);

                    $goodSubtotal += (float) $item->quantity_received * (float) $item->unit_cost;
                }

                if ($item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::query()->lockForUpdate()->find($item->purchase_order_item_id);

                    $poItem?->update([
                        'quantity_received' => (float) $poItem->quantity_received + (float) $item->quantity_received,
                    ]);
                }
            }

            $grn->update(['status' => 'confirmed']);

            if ($grn->purchase_order_id) {
                $this->recomputePurchaseOrderStatus($grn->purchase_order_id);
            }

            $this->autoCreateSupplierBill($grn, $goodSubtotal);

            return $grn->fresh(['items', 'purchaseOrder']);
        });
    }

    protected function normalizeItems(array $items): array
    {
        return array_map(fn (array $item) => [
            'product_id' => $item['product_id'],
            'product_variant_id' => $item['product_variant_id'] ?? null,
            'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
            'quantity_received' => $item['quantity_received'],
            'unit_cost' => $item['unit_cost'],
            'condition' => $item['condition'] ?? 'good',
        ], $items);
    }

    protected function recomputePurchaseOrderStatus(int $purchaseOrderId): void
    {
        $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->find($purchaseOrderId);

        if (! $purchaseOrder) {
            return;
        }

        $items = $purchaseOrder->items;
        $allReceived = $items->every(fn ($item) => (float) $item->quantity_received >= (float) $item->quantity_ordered);
        $anyReceived = $items->contains(fn ($item) => (float) $item->quantity_received > 0);

        $status = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $purchaseOrder->status);

        if ($status !== $purchaseOrder->status) {
            $purchaseOrder->update(['status' => $status]);
        }
    }

    protected function autoCreateSupplierBill(GoodsReceivedNote $grn, float $subtotal): ?SupplierBill
    {
        if (SupplierBill::where('grn_id', $grn->id)->exists() || $subtotal <= 0) {
            return null;
        }

        $supplier = Supplier::find($grn->supplier_id);
        $dueDate = Carbon::parse($grn->received_date)->addDays($supplier?->payment_terms_days ?? 30);
        $totalAmount = round($subtotal, 2);

        $bill = SupplierBill::create([
            'supplier_id' => $grn->supplier_id,
            'grn_id' => $grn->id,
            'purchase_order_id' => $grn->purchase_order_id,
            'reference_number' => 'BILL-'.$grn->reference_number,
            'bill_date' => $grn->received_date,
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $totalAmount,
            'tax_amount' => 0,
            'total_amount' => $totalAmount,
            'status' => 'unpaid',
        ]);

        $inventoryAccount = $this->resolveAccount($grn->company_id, self::INVENTORY_ACCOUNT_CODE);
        $payableAccount = $this->resolveAccount($grn->company_id, self::ACCOUNTS_PAYABLE_ACCOUNT_CODE);

        $journalEntry = $this->journalEntryService->postModuleEntry([
            'reference_number' => 'GRN-JE-'.$grn->reference_number,
            'entry_date' => $grn->received_date,
            'description' => "Goods received from supplier for {$grn->reference_number}",
            'source_module' => 'procurement',
            'source_id' => $grn->id,
            'lines' => [
                ['account_id' => $inventoryAccount->id, 'debit' => $totalAmount, 'credit' => 0, 'description' => 'Inventory received'],
                ['account_id' => $payableAccount->id, 'debit' => 0, 'credit' => $totalAmount, 'description' => 'Accounts payable'],
            ],
        ]);

        $bill->update(['journal_entry_id' => $journalEntry->id]);

        return $bill;
    }

    protected function resolveAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."],
            ]);
        }

        return $account;
    }
}
