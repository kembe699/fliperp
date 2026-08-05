<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\TaxRate;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(protected PromotionService $promotionService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Quotation::query()
            ->with('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->latest('quotation_date')
            ->paginate($perPage);
    }

    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::find($data['customer_id']);
            $date = $data['quotation_date'] ?? now()->toDateString();

            ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'items' => $items] = $this->computeTotals(
                $data['items'], $data['price_list_id'] ?? null, $customer, $date,
            );

            $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);

            $quotation = Quotation::create([
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'],
                'price_list_id' => $data['price_list_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? $this->generateReferenceNumber(),
                'quotation_date' => $date,
                'valid_until' => $data['valid_until'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => round($subtotal - $discountAmount + $taxAmount, 2),
            ]);

            $quotation->items()->createMany($items);

            return $quotation->fresh('items');
        });
    }

    public function update(Quotation $quotation, array $data): Quotation
    {
        if ($quotation->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft quotations can be edited.'],
            ]);
        }

        return DB::transaction(function () use ($quotation, $data) {
            $payload = collect($data)->except('items')->toArray();

            if (isset($data['items'])) {
                $customer = Customer::find($data['customer_id'] ?? $quotation->customer_id);
                $priceListId = array_key_exists('price_list_id', $data) ? $data['price_list_id'] : $quotation->price_list_id;
                $date = $data['quotation_date'] ?? $quotation->quotation_date->toDateString();

                ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'items' => $items] = $this->computeTotals(
                    $data['items'], $priceListId, $customer, $date,
                );

                $discountAmount = round((float) ($data['discount_amount'] ?? $quotation->discount_amount), 2);

                $payload['subtotal'] = $subtotal;
                $payload['tax_amount'] = $taxAmount;
                $payload['discount_amount'] = $discountAmount;
                $payload['total_amount'] = round($subtotal - $discountAmount + $taxAmount, 2);
            }

            $quotation->update($payload);

            if (isset($data['items'])) {
                $quotation->items()->delete();
                $quotation->items()->createMany($items);
            }

            return $quotation->fresh('items');
        });
    }

    public function delete(Quotation $quotation): void
    {
        if ($quotation->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft quotations can be deleted.'],
            ]);
        }

        $quotation->delete();
    }

    public function send(Quotation $quotation): Quotation
    {
        $this->assertStatus($quotation, 'draft', 'sent');
        $quotation->update(['status' => 'sent']);

        return $quotation;
    }

    public function accept(Quotation $quotation): Quotation
    {
        $this->assertStatus($quotation, 'sent', 'accepted');
        $quotation->update(['status' => 'accepted']);

        return $quotation;
    }

    public function reject(Quotation $quotation): Quotation
    {
        $this->assertStatus($quotation, 'sent', 'rejected');
        $quotation->update(['status' => 'rejected']);

        return $quotation;
    }

    public function convertToInvoice(Quotation $quotation): Invoice
    {
        if ($quotation->status !== 'accepted') {
            throw ValidationException::withMessages([
                'status' => ['Only an accepted quotation can be converted to an invoice.'],
            ]);
        }

        return DB::transaction(function () use ($quotation) {
            $quotation = Quotation::query()->lockForUpdate()->with('items')->findOrFail($quotation->id);

            // Re-check after the lock: without it, a duplicate conversion
            // request creates a second invoice from the same quotation
            // instead of being rejected as already converted.
            if ($quotation->status !== 'accepted') {
                throw ValidationException::withMessages([
                    'status' => ['Only an accepted quotation can be converted to an invoice.'],
                ]);
            }

            $invoice = Invoice::create([
                'branch_id' => $quotation->branch_id,
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'reference_number' => 'INV-'.$quotation->reference_number,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'draft',
                'subtotal' => $quotation->subtotal,
                'tax_amount' => $quotation->tax_amount,
                'discount_amount' => $quotation->discount_amount,
                'total_amount' => $quotation->total_amount,
                'amount_paid' => 0,
                'created_by' => Auth::id(),
            ]);

            $invoice->items()->createMany($quotation->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate_id' => $item->tax_rate_id,
                'discount_amount' => $item->discount_amount,
                'line_total' => $item->line_total,
            ])->all());

            $quotation->update(['status' => 'converted']);

            return $invoice->fresh('items');
        });
    }

    public function buildPdf(Quotation $quotation): DomPdf
    {
        $quotation->loadMissing(['items.product', 'items.variant', 'customer', 'branch']);
        $company = Company::find($quotation->company_id);

        return Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'company' => $company,
            'branch' => $quotation->branch,
            'customer' => $quotation->customer,
            'currencyCode' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');
    }

    protected function assertStatus(Quotation $quotation, string $expected, string $target): void
    {
        if ($quotation->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Only a {$expected} quotation can transition to {$target}."],
            ]);
        }
    }

    protected function computeTotals(array $items, ?int $priceListId, ?Customer $customer, string $date): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $normalized = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $quantity = (float) $item['quantity'];

            $unitPrice = isset($item['unit_price'])
                ? (float) $item['unit_price']
                : $this->resolveUnitPrice($product, $item['product_variant_id'] ?? null, $priceListId);

            $discount = isset($item['discount_amount'])
                ? (float) $item['discount_amount']
                : $this->promotionService->applicableDiscount($product, $customer, $date, $quantity);

            $lineTotal = round(($quantity * $unitPrice) - $discount, 2);

            $itemTax = 0.0;
            if (! empty($item['tax_rate_id'])) {
                $taxRate = TaxRate::findOrFail($item['tax_rate_id']);
                $itemTax = round($lineTotal * ((float) $taxRate->rate / 100), 2);
            }

            $subtotal += $lineTotal;
            $taxAmount += $itemTax;

            $normalized[] = [
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate_id' => $item['tax_rate_id'] ?? null,
                'discount_amount' => round($discount, 2),
                'line_total' => $lineTotal,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'taxAmount' => round($taxAmount, 2),
            'items' => $normalized,
        ];
    }

    protected function resolveUnitPrice(Product $product, ?int $productVariantId, ?int $priceListId): float
    {
        if ($priceListId) {
            $priceListItem = PriceListItem::query()
                ->where('price_list_id', $priceListId)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $productVariantId)
                ->first();

            if ($priceListItem) {
                return (float) $priceListItem->price;
            }
        }

        return (float) $product->selling_price;
    }

    protected function generateReferenceNumber(): string
    {
        return 'QUO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
