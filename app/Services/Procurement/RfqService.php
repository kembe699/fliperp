<?php

namespace App\Services\Procurement;

use App\Mail\DocumentMail;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqSupplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Requests for quotation: ask suppliers for prices on a list of items, email it
 * to all or some of them, record what each quoted, and turn the chosen
 * supplier's quote into a purchase order.
 */
class RfqService
{
    public const LOADED = ['items.product', 'items.variant', 'suppliers.supplier', 'suppliers.quotes', 'purchaseOrder', 'selectedSupplier', 'branch', 'warehouse'];

    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Rfq::query()->with('suppliers.supplier')->withCount('items')->latest('issue_date')->latest('id')->paginate($perPage);
    }

    public function create(array $data): Rfq
    {
        return DB::transaction(function () use ($data) {
            $rfq = Rfq::create([
                'branch_id' => $data['branch_id'] ?? Auth::user()->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? $this->nextReference(),
                'title' => $data['title'],
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'response_due_date' => $data['response_due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);
            $this->syncLines($rfq, $data);

            return $rfq->load(self::LOADED);
        });
    }

    /** Only a draft can be edited: once sent, suppliers are quoting on what they received. */
    public function update(Rfq $rfq, array $data): Rfq
    {
        $this->mustBe($rfq, ['draft'], 'Only a draft RFQ can be edited.');

        return DB::transaction(function () use ($rfq, $data) {
            $rfq->update(collect($data)->only(['branch_id', 'warehouse_id', 'title', 'issue_date', 'response_due_date', 'notes'])->all());
            $this->syncLines($rfq, $data);

            return $rfq->load(self::LOADED);
        });
    }

    public function delete(Rfq $rfq): void
    {
        $this->mustBe($rfq, ['draft', 'cancelled'], 'Only a draft or cancelled RFQ can be deleted.');
        $rfq->delete();
    }

    public function cancel(Rfq $rfq): Rfq
    {
        $this->mustBe($rfq, ['draft', 'sent'], 'This RFQ can no longer be cancelled.');
        $rfq->update(['status' => 'cancelled']);

        return $rfq->load(self::LOADED);
    }

    /**
     * Emails the RFQ (PDF attached, addressed to each supplier by name) to the
     * given suppliers on it, or to all of them. Suppliers without an email
     * address are skipped and reported back.
     *
     * @param  array<int>|null  $supplierIds
     * @return array{sent: array<int, string>, skipped: array<int, string>}
     */
    public function send(Rfq $rfq, ?array $supplierIds = null): array
    {
        $this->mustBe($rfq, ['draft', 'sent'], 'Only a draft or sent RFQ can be emailed.');
        $rfq->load(self::LOADED);
        if ($rfq->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => ['Add the items you need before sending the RFQ.']]);
        }

        $targets = $rfq->suppliers->when($supplierIds, fn ($all) => $all->whereIn('supplier_id', $supplierIds));
        if ($targets->isEmpty()) {
            throw ValidationException::withMessages(['suppliers' => ['Choose at least one supplier on this RFQ to send it to.']]);
        }

        $company = Company::find($rfq->company_id);
        $sent = [];
        $skipped = [];
        foreach ($targets as $line) {
            $supplier = $line->supplier;
            if (! $supplier?->email) {
                $skipped[] = $supplier?->name ?? 'Supplier #'.$line->supplier_id;

                continue;
            }
            Mail::to($supplier->email)->send(new DocumentMail(
                documentType: 'Request for Quotation',
                referenceNumber: $rfq->reference_number,
                recipientName: $supplier->name,
                companyName: $company?->name ?? config('app.name'),
                pdfContent: $this->pdf($rfq, $line)->output(),
                pdfFilename: "rfq-{$rfq->reference_number}.pdf",
            ));
            $line->update(['sent_at' => now(), 'sent_to' => $supplier->email]);
            $sent[] = "{$supplier->name} <{$supplier->email}>";
        }

        if ($sent && $rfq->status === 'draft') {
            $rfq->update(['status' => 'sent']);
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Records one supplier's reply: a unit price per item (items left out are
     * not quoted), the delivery time and any notes.
     *
     * @param  array{prices: array<int, array{rfq_item_id:int, unit_price:float|string}>, delivery_days?:int|null, notes?:string|null}  $data
     */
    public function recordQuote(Rfq $rfq, int $supplierId, array $data): Rfq
    {
        $this->mustBe($rfq, ['draft', 'sent'], 'Quotes can only be recorded on an open RFQ.');
        $line = $this->supplierLine($rfq, $supplierId);
        $itemIds = $rfq->items()->pluck('id')->all();

        DB::transaction(function () use ($line, $data, $itemIds) {
            $line->quotes()->delete();
            foreach ($data['prices'] as $price) {
                if (! in_array((int) $price['rfq_item_id'], $itemIds, true) || $price['unit_price'] === null || $price['unit_price'] === '') {
                    continue;
                }
                $line->quotes()->create(['rfq_item_id' => $price['rfq_item_id'], 'unit_price' => round((float) $price['unit_price'], 2)]);
            }
            $line->update(['quoted_at' => now(), 'delivery_days' => $data['delivery_days'] ?? null, 'quote_notes' => $data['notes'] ?? null]);
        });

        return $rfq->load(self::LOADED);
    }

    /**
     * Turns the RFQ into a purchase order for the chosen supplier, at the prices
     * that supplier quoted (an item they didn't price uses the product's cost
     * price), and submits it for approval like a PO raised from the form.
     *
     * @param  array{warehouse_id?:int|null, branch_id?:int|null, order_date?:string|null, expected_delivery_date?:string|null}  $data
     */
    public function convert(Rfq $rfq, int $supplierId, array $data = []): PurchaseOrder
    {
        $this->mustBe($rfq, ['draft', 'sent'], 'This RFQ has already been converted or cancelled.');
        $rfq->load('items');
        $line = $this->supplierLine($rfq, $supplierId);
        $prices = $line->quotes()->pluck('unit_price', 'rfq_item_id');

        $warehouseId = $data['warehouse_id'] ?? $rfq->warehouse_id;
        $branchId = $data['branch_id'] ?? $rfq->branch_id ?? Auth::user()->branch_id;
        if (! $warehouseId || ! $branchId) {
            throw ValidationException::withMessages(['warehouse_id' => ['Choose the branch and warehouse the goods will be delivered to.']]);
        }

        return DB::transaction(function () use ($rfq, $line, $prices, $warehouseId, $branchId, $data, $supplierId) {
            $orderDate = $data['order_date'] ?? now()->toDateString();
            $po = $this->purchaseOrderService->create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'supplier_id' => $supplierId,
                'reference_number' => $this->poReference($rfq),
                'order_date' => $orderDate,
                'expected_delivery_date' => $data['expected_delivery_date']
                    ?? ($line->delivery_days ? now()->parse($orderDate)->addDays($line->delivery_days)->toDateString() : null),
                'notes' => trim("From {$rfq->reference_number}: {$rfq->title}".($rfq->notes ? "\n{$rfq->notes}" : '')),
                'items' => $rfq->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity_ordered' => (float) $item->quantity,
                    'unit_cost' => (float) ($prices[$item->id] ?? Product::find($item->product_id)?->cost_price ?? 0),
                ])->all(),
            ]);

            $rfq->update(['status' => 'converted', 'selected_supplier_id' => $supplierId, 'purchase_order_id' => $po->id]);

            // Like a PO raised from the form: straight to approval, when this user may submit.
            if (Auth::user()?->can('purchase-orders.submit')) {
                $po = $this->purchaseOrderService->submit($po);
            }

            return $po;
        });
    }

    /** The RFQ as a PDF — addressed to one supplier when sending it, or generic for download. */
    public function pdf(Rfq $rfq, ?RfqSupplier $to = null): DomPdf
    {
        $rfq->loadMissing(self::LOADED);
        $company = Company::find($rfq->company_id);

        return Pdf::loadView('pdf.rfq', [
            'rfq' => $rfq,
            'company' => $company,
            'branch' => $rfq->branch,
            'supplier' => $to?->supplier,
            'currencyCode' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');
    }

    protected function syncLines(Rfq $rfq, array $data): void
    {
        if (array_key_exists('items', $data)) {
            $rfq->items()->delete();
            $rfq->items()->createMany(array_map(fn ($item) => [
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
            ], $data['items']));
        }
        if (array_key_exists('supplier_ids', $data)) {
            $ids = array_values(array_unique(array_map('intval', $data['supplier_ids'])));
            $rfq->suppliers()->whereNotIn('supplier_id', $ids)->delete();
            foreach ($ids as $id) {
                $rfq->suppliers()->firstOrCreate(['supplier_id' => $id]);
            }
        }
    }

    protected function supplierLine(Rfq $rfq, int $supplierId): RfqSupplier
    {
        $line = $rfq->suppliers()->where('supplier_id', $supplierId)->first();
        if (! $line) {
            throw ValidationException::withMessages(['supplier_id' => ['That supplier is not on this RFQ.']]);
        }

        return $line;
    }

    protected function mustBe(Rfq $rfq, array $statuses, string $message): void
    {
        if (! in_array($rfq->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    /** RFQ-0001, RFQ-0002 … per company. */
    protected function nextReference(): string
    {
        $n = Rfq::query()->count() + 1;
        do {
            $ref = 'RFQ-'.str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (Rfq::query()->where('reference_number', $ref)->exists());

        return $ref;
    }

    protected function poReference(Rfq $rfq): string
    {
        $base = 'PO-'.$rfq->reference_number;
        $ref = $base;
        $n = 2;
        while (PurchaseOrder::query()->where('reference_number', $ref)->exists()) {
            $ref = $base.'-'.$n++;
        }

        return $ref;
    }
}
