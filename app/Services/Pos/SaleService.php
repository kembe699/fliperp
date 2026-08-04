<?php

namespace App\Services\Pos;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\TaxRate;
use App\Notifications\SaleVoided;
use App\Services\Finance\JournalEntryService;
use App\Services\Inventory\StockMovementService;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The choke point for sale completion/void/refund: stock movements, the
 * revenue/tax/COGS journal entry and table status all happen here,
 * atomically, mirroring GrnService's confirm() pattern.
 */
class SaleService
{
    public const CASH_ACCOUNT_CODE = '1000';

    public const ACCOUNTS_RECEIVABLE_ACCOUNT_CODE = '1100';

    public const INVENTORY_ACCOUNT_CODE = '1200';

    public const TAX_PAYABLE_ACCOUNT_CODE = '2300';

    public const SALES_REVENUE_ACCOUNT_CODE = '4000';

    public const COGS_ACCOUNT_CODE = '5300';

    public function __construct(
        protected StockMovementService $stockMovementService,
        protected JournalEntryService $journalEntryService,
        protected CashDrawerService $cashDrawerService,
        protected NotificationRecipientResolver $recipientResolver,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Sale::query()
            ->with('items', 'payments')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['served_by'] ?? null, fn ($query, $id) => $query->where('served_by', $id))
            ->when($filters['cash_drawer_session_id'] ?? null, fn ($query, $id) => $query->where('cash_drawer_session_id', $id))
            ->when(
                $filters['payment_type_id'] ?? null,
                fn ($query, $id) => $query->whereHas('payments', fn ($payments) => $payments->where('payment_type_id', $id)),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('reference_number', 'like', "%{$search}%"))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('sale_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('sale_date', '<=', $to))
            ->latest('sale_date')
            ->paginate($perPage);
    }

    public function heldForCurrentSession(): Collection
    {
        $session = $this->cashDrawerService->current();

        if (! $session) {
            return collect();
        }

        return Sale::query()
            ->where('cash_drawer_session_id', $session->id)
            ->where('status', 'held')
            ->with('items', 'payments')
            ->latest()
            ->get();
    }

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'items' => $items] = $this->computeTotals($data['items']);

            $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);
            $totalAmount = round($subtotal - $discountAmount + $taxAmount, 2);

            $session = $this->cashDrawerService->current();

            $sale = Sale::create([
                'branch_id' => $data['branch_id'] ?? $user->branch_id,
                'warehouse_id' => $data['warehouse_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'cash_drawer_session_id' => $session?->id,
                'table_id' => $data['table_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? $this->generateReferenceNumber(),
                'sale_type' => $data['sale_type'] ?? 'pos',
                'status' => 'held',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'served_by' => $user->id,
                'sale_date' => $data['sale_date'] ?? now()->toDateString(),
            ]);

            $sale->items()->createMany($items);

            $this->syncTableStatus($sale, 'occupied');

            return $sale->fresh(['items', 'payments']);
        });
    }

    public function update(Sale $sale, array $data): Sale
    {
        if ($sale->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => ['Only held sales can be edited.'],
            ]);
        }

        return DB::transaction(function () use ($sale, $data) {
            $previousTableId = $sale->table_id;

            $payload = collect($data)->except('items')->toArray();

            if (isset($data['items'])) {
                ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'items' => $items] = $this->computeTotals($data['items']);
                $discountAmount = round((float) ($data['discount_amount'] ?? $sale->discount_amount), 2);

                $payload['subtotal'] = $subtotal;
                $payload['tax_amount'] = $taxAmount;
                $payload['discount_amount'] = $discountAmount;
                $payload['total_amount'] = round($subtotal - $discountAmount + $taxAmount, 2);
            }

            $sale->update($payload);

            if (isset($data['items'])) {
                $sale->items()->delete();
                $sale->items()->createMany($items);
            }

            if ($previousTableId && $previousTableId !== $sale->table_id) {
                RestaurantTable::query()->find($previousTableId)?->update(['status' => 'available']);
            }

            $this->syncTableStatus($sale, 'occupied');

            return $sale->fresh(['items', 'payments']);
        });
    }

    public function delete(Sale $sale): void
    {
        if ($sale->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => ['Only held sales can be deleted.'],
            ]);
        }

        DB::transaction(function () use ($sale) {
            $this->syncTableStatus($sale, 'available');
            $sale->delete();
        });
    }

    public function hold(Sale $sale): Sale
    {
        if ($sale->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => ['Only a sale currently held can be re-held.'],
            ]);
        }

        return $sale;
    }

    public function addPayment(Sale $sale, array $data): SalePayment
    {
        if (! in_array($sale->status, ['held', 'completed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Payments can only be added to held or completed sales.'],
            ]);
        }

        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            // Re-check after the lock: guards against a payment landing on a
            // sale that was voided/refunded by a concurrent request in the
            // gap between the check above and acquiring this lock.
            if (! in_array($sale->status, ['held', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Payments can only be added to held or completed sales.'],
                ]);
            }

            $amount = round((float) $data['amount'], 2);

            $payment = SalePayment::create([
                'sale_id' => $sale->id,
                'payment_type_id' => $data['payment_type_id'],
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
            ]);

            if ($sale->status === 'completed') {
                $sale->update(['amount_paid' => round((float) $sale->amount_paid + $amount, 2)]);

                $receivableAccount = $this->resolveAccount($sale->company_id, self::ACCOUNTS_RECEIVABLE_ACCOUNT_CODE);
                $cashAccount = $this->resolveAccount($sale->company_id, self::CASH_ACCOUNT_CODE);

                $this->journalEntryService->postModuleEntry([
                    'reference_number' => 'SPAY-'.$sale->reference_number.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'entry_date' => now()->toDateString(),
                    'description' => "Credit payment received for sale {$sale->reference_number}",
                    'source_module' => 'pos',
                    'source_id' => $sale->id,
                    'lines' => [
                        ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Cash received'],
                        ['account_id' => $receivableAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Accounts receivable settled'],
                    ],
                ]);
            }

            return $payment;
        });
    }

    public function complete(Sale $sale): Sale
    {
        if ($sale->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => ['Only held sales can be completed.'],
            ]);
        }

        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()->lockForUpdate()->with('items', 'payments', 'customer')->findOrFail($sale->id);

            // Re-check after the lock: a double-click or duplicate retry that
            // both passed the pre-transaction check would otherwise both
            // reach here, and the second (blocked on the lock until the
            // first commits) would go on to book stock movements and a
            // journal entry a second time for the same sale instead of
            // being rejected as already completed.
            if ($sale->status !== 'held') {
                throw ValidationException::withMessages([
                    'status' => ['Only held sales can be completed.'],
                ]);
            }

            if ($sale->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['A sale with no items cannot be completed.'],
                ]);
            }

            $paid = round((float) $sale->payments->sum('amount'), 2);
            $totalAmount = round((float) $sale->total_amount, 2);
            $customer = $sale->customer;
            $isCredit = $customer && $customer->customer_type === 'credit';

            if (! $isCredit && abs($paid - $totalAmount) > 0.01) {
                throw ValidationException::withMessages([
                    'payments' => ["Sale payments ({$paid}) must equal the total amount ({$totalAmount}) unless the customer has credit terms."],
                ]);
            }

            if ($paid > $totalAmount + 0.01) {
                throw ValidationException::withMessages([
                    'payments' => ['Sale payments cannot exceed the total amount.'],
                ]);
            }

            $outstandingFromThisSale = round($totalAmount - $paid, 2);

            if ($isCredit && $outstandingFromThisSale > 0) {
                $existingOutstanding = Sale::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->where('id', '!=', $sale->id)
                    ->get()
                    ->sum(fn ($other) => (float) $other->total_amount - (float) $other->amount_paid);

                if (round($existingOutstanding + $outstandingFromThisSale, 2) > (float) $customer->credit_limit) {
                    throw ValidationException::withMessages([
                        'customer' => ['This sale would exceed the customer\'s credit limit.'],
                    ]);
                }
            }

            $cogsTotal = 0.0;

            foreach ($sale->items as $item) {
                $product = Product::findOrFail($item->product_id);

                $this->stockMovementService->record([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'movement_type' => 'sale',
                    'quantity' => -abs((float) $item->quantity),
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                ]);

                $cogsTotal += (float) $item->quantity * (float) $product->cost_price;
            }

            $cogsTotal = round($cogsTotal, 2);

            $journalEntry = $this->postSaleJournalEntry($sale, $paid, $outstandingFromThisSale, $cogsTotal);

            $sale->update([
                'status' => 'completed',
                'amount_paid' => $paid,
                'journal_entry_id' => $journalEntry->id,
            ]);

            $this->syncTableStatus($sale, 'available');

            return $sale->fresh(['items', 'payments']);
        });
    }

    public function void(Sale $sale): Sale
    {
        return $this->reverse($sale, 'voided');
    }

    public function refund(Sale $sale): Sale
    {
        return $this->reverse($sale, 'refunded');
    }

    protected function reverse(Sale $sale, string $status): Sale
    {
        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => ['Only a completed sale can be '.($status === 'voided' ? 'voided' : 'refunded').'.'],
            ]);
        }

        $reversed = DB::transaction(function () use ($sale, $status) {
            $sale = Sale::query()->lockForUpdate()->with('items')->findOrFail($sale->id);

            // Re-check after the lock — see complete() for why: without this
            // a duplicate void/refund request reverses the stock and journal
            // entry a second time instead of being rejected.
            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'status' => ['Only a completed sale can be '.($status === 'voided' ? 'voided' : 'refunded').'.'],
                ]);
            }

            foreach ($sale->items as $item) {
                $this->stockMovementService->record([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'movement_type' => 'return',
                    'quantity' => abs((float) $item->quantity),
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                ]);
            }

            if ($sale->journal_entry_id) {
                $this->journalEntryService->reverse($sale->journalEntry);
            }

            $sale->update(['status' => $status]);

            $this->syncTableStatus($sale, 'available');

            return $sale->fresh(['items', 'payments']);
        });

        if ($status === 'voided') {
            $recipients = $this->recipientResolver->usersWithPermission(
                $reversed->company_id,
                'users.view',
                $reversed->branch_id,
            )->reject(fn ($user) => $user->id === Auth::id());
            Notification::send($recipients, new SaleVoided($reversed));
        }

        return $reversed;
    }

    protected function postSaleJournalEntry(Sale $sale, float $paid, float $outstanding, float $cogsTotal): JournalEntry
    {
        $cashCollected = round($paid, 2);
        $revenueAmount = round((float) $sale->subtotal - (float) $sale->discount_amount, 2);
        $taxAmount = round((float) $sale->tax_amount, 2);

        $lines = [];

        if ($cashCollected > 0) {
            $lines[] = [
                'account_id' => $this->resolveAccount($sale->company_id, self::CASH_ACCOUNT_CODE)->id,
                'debit' => $cashCollected,
                'credit' => 0,
                'description' => 'Payments received at sale',
            ];
        }

        if ($outstanding > 0) {
            $lines[] = [
                'account_id' => $this->resolveAccount($sale->company_id, self::ACCOUNTS_RECEIVABLE_ACCOUNT_CODE)->id,
                'debit' => $outstanding,
                'credit' => 0,
                'description' => 'Balance due from customer',
            ];
        }

        $lines[] = [
            'account_id' => $this->resolveAccount($sale->company_id, self::SALES_REVENUE_ACCOUNT_CODE)->id,
            'debit' => 0,
            'credit' => $revenueAmount,
            'description' => 'Sales revenue',
        ];

        if ($taxAmount > 0) {
            $lines[] = [
                'account_id' => $this->resolveAccount($sale->company_id, self::TAX_PAYABLE_ACCOUNT_CODE)->id,
                'debit' => 0,
                'credit' => $taxAmount,
                'description' => 'Tax collected',
            ];
        }

        if ($cogsTotal > 0) {
            $lines[] = [
                'account_id' => $this->resolveAccount($sale->company_id, self::COGS_ACCOUNT_CODE)->id,
                'debit' => $cogsTotal,
                'credit' => 0,
                'description' => 'Cost of goods sold',
            ];
            $lines[] = [
                'account_id' => $this->resolveAccount($sale->company_id, self::INVENTORY_ACCOUNT_CODE)->id,
                'debit' => 0,
                'credit' => $cogsTotal,
                'description' => 'Inventory sold',
            ];
        }

        return $this->journalEntryService->postModuleEntry([
            'reference_number' => 'SALE-JE-'.$sale->reference_number,
            'entry_date' => $sale->sale_date,
            'description' => "Sale completed for {$sale->reference_number}",
            'source_module' => 'pos',
            'source_id' => $sale->id,
            'lines' => $lines,
        ]);
    }

    protected function computeTotals(array $items): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $normalized = [];

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $discount = (float) ($item['discount_amount'] ?? 0);
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
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'taxAmount' => round($taxAmount, 2),
            'items' => $normalized,
        ];
    }

    protected function syncTableStatus(Sale $sale, string $status): void
    {
        if ($sale->sale_type !== 'dine_in' || ! $sale->table_id) {
            return;
        }

        RestaurantTable::query()->find($sale->table_id)?->update(['status' => $status]);
    }

    protected function generateReferenceNumber(): string
    {
        return 'SALE-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
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
