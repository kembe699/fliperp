<?php

namespace App\Services\Sales;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\Finance\JournalEntryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The choke point for invoice send/cancel: the AR/Revenue/Tax journal entry
 * is posted here, atomically, mirroring GrnService/SaleService.
 */
class InvoiceService
{
    public const ACCOUNTS_RECEIVABLE_ACCOUNT_CODE = '1100';

    public const SALES_REVENUE_ACCOUNT_CODE = '4000';

    public const TAX_PAYABLE_ACCOUNT_CODE = '2300';

    public function __construct(
        protected PromotionService $promotionService,
        protected JournalEntryService $journalEntryService,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('invoice_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('invoice_date', '<=', $to))
            ->latest('invoice_date')
            ->paginate($perPage);
    }

    public function overdue(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->overdue()
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->latest('due_date')
            ->paginate($perPage);
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::find($data['customer_id']);
            $date = $data['invoice_date'] ?? now()->toDateString();

            ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'items' => $items] = $this->computeTotals(
                $data['items'], $data['price_list_id'] ?? null, $customer, $date,
            );

            $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);

            $invoice = Invoice::create([
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'],
                'quotation_id' => $data['quotation_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? $this->generateReferenceNumber(),
                'invoice_date' => $date,
                'due_date' => $data['due_date'],
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => round($subtotal - $discountAmount + $taxAmount, 2),
                'amount_paid' => 0,
                'created_by' => Auth::id(),
            ]);

            $invoice->items()->createMany($items);

            return $invoice->fresh('items');
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft invoices can be edited; once sent, use a credit note flow instead.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $payload = collect($data)->except('items')->toArray();

            if (isset($data['items'])) {
                $customer = Customer::find($data['customer_id'] ?? $invoice->customer_id);
                $date = $data['invoice_date'] ?? $invoice->invoice_date->toDateString();

                ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'items' => $items] = $this->computeTotals(
                    $data['items'], $data['price_list_id'] ?? null, $customer, $date,
                );

                $discountAmount = round((float) ($data['discount_amount'] ?? $invoice->discount_amount), 2);

                $payload['subtotal'] = $subtotal;
                $payload['tax_amount'] = $taxAmount;
                $payload['discount_amount'] = $discountAmount;
                $payload['total_amount'] = round($subtotal - $discountAmount + $taxAmount, 2);
            }

            $invoice->update($payload);

            if (isset($data['items'])) {
                $invoice->items()->delete();
                $invoice->items()->createMany($items);
            }

            return $invoice->fresh('items');
        });
    }

    public function delete(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft' || (float) $invoice->amount_paid > 0 || $invoice->journal_entry_id) {
            throw ValidationException::withMessages([
                'invoice' => ['Only a draft invoice with no payments or posted journal entry can be deleted.'],
            ]);
        }

        $invoice->delete();
    }

    public function send(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only a draft invoice can be sent.'],
            ]);
        }

        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            // Re-check after the lock: without it, a duplicate send request
            // posts a second revenue journal entry for the same invoice
            // instead of being rejected as already sent.
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['Only a draft invoice can be sent.'],
                ]);
            }

            $revenueAmount = round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2);
            $taxAmount = round((float) $invoice->tax_amount, 2);
            $totalAmount = round((float) $invoice->total_amount, 2);

            $lines = [
                ['account_id' => $this->resolveAccount($invoice->company_id, self::ACCOUNTS_RECEIVABLE_ACCOUNT_CODE)->id, 'debit' => $totalAmount, 'credit' => 0, 'description' => 'Amount billed to customer'],
                ['account_id' => $this->resolveAccount($invoice->company_id, self::SALES_REVENUE_ACCOUNT_CODE)->id, 'debit' => 0, 'credit' => $revenueAmount, 'description' => 'Sales revenue'],
            ];

            if ($taxAmount > 0) {
                $lines[] = ['account_id' => $this->resolveAccount($invoice->company_id, self::TAX_PAYABLE_ACCOUNT_CODE)->id, 'debit' => 0, 'credit' => $taxAmount, 'description' => 'Tax collected'];
            }

            $journalEntry = $this->journalEntryService->postModuleEntry([
                'reference_number' => 'INV-JE-'.$invoice->reference_number,
                'entry_date' => $invoice->invoice_date,
                'description' => "Invoice sent to customer for {$invoice->reference_number}",
                'source_module' => 'sales',
                'source_id' => $invoice->id,
                'lines' => $lines,
            ]);

            $invoice->update(['status' => 'sent', 'journal_entry_id' => $journalEntry->id]);

            return $invoice->fresh('items');
        });
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, ['draft', 'sent'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only a draft or sent invoice can be cancelled.'],
            ]);
        }

        if ((float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages([
                'invoice' => ['Cannot cancel an invoice that already has payments applied; use a credit note flow instead.'],
            ]);
        }

        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            // Re-check after the lock: without it, a duplicate cancel
            // request would try to reverse the same journal entry twice
            // instead of being rejected as already cancelled.
            if (! in_array($invoice->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only a draft or sent invoice can be cancelled.'],
                ]);
            }

            if ((float) $invoice->amount_paid > 0) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot cancel an invoice that already has payments applied; use a credit note flow instead.'],
                ]);
            }

            if ($invoice->journal_entry_id) {
                $this->journalEntryService->reverse($invoice->journalEntry);
            }

            $invoice->update(['status' => 'cancelled']);

            return $invoice->fresh('items');
        });
    }

    public function refreshStatus(Invoice $invoice): Invoice
    {
        $status = $this->determineStatus($invoice);

        if ($status !== $invoice->status) {
            $invoice->update(['status' => $status]);
        }

        return $invoice;
    }

    protected function determineStatus(Invoice $invoice): string
    {
        if ($invoice->status === 'cancelled') {
            return $invoice->status;
        }

        $totalAmount = (float) $invoice->total_amount;
        $amountPaid = (float) $invoice->amount_paid;

        if ($totalAmount > 0 && $amountPaid >= $totalAmount) {
            return 'paid';
        }

        if ($invoice->status !== 'draft' && Carbon::parse($invoice->due_date)->isBefore(Carbon::today())) {
            return 'overdue';
        }

        if ($invoice->status === 'draft') {
            return 'draft';
        }

        return $amountPaid > 0 ? 'partially_paid' : 'sent';
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
                'description' => $item['description'] ?? null,
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
        return 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
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
