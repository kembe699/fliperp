<?php

namespace App\Services\Procurement;

use App\Models\ChartOfAccount;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Services\Finance\JournalEntryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public const ACCOUNTS_PAYABLE_ACCOUNT_CODE = '2000';

    public const CASH_ACCOUNT_CODE = '1000';

    public function __construct(
        protected SupplierBillService $supplierBillService,
        protected JournalEntryService $journalEntryService,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return SupplierPayment::query()
            ->when($filters['supplier_bill_id'] ?? null, fn ($query, $id) => $query->where('supplier_bill_id', $id))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->latest('payment_date')
            ->paginate($perPage);
    }

    public function create(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $bill = SupplierBill::query()->lockForUpdate()->findOrFail($data['supplier_bill_id']);

            $amount = round((float) $data['amount'], 2);
            $balanceDue = round((float) $bill->total_amount - (float) $bill->amount_paid, 2);

            if ($amount > $balanceDue) {
                throw ValidationException::withMessages([
                    'amount' => ["Payment amount exceeds the outstanding balance of {$balanceDue}."],
                ]);
            }

            $bill->update(['amount_paid' => (float) $bill->amount_paid + $amount]);
            $this->supplierBillService->refreshStatus($bill);

            $payableAccount = $this->resolveAccount($bill->company_id, self::ACCOUNTS_PAYABLE_ACCOUNT_CODE);
            $cashAccount = $this->resolveAccount($bill->company_id, self::CASH_ACCOUNT_CODE);

            // Always generated, never the caller's own payment reference:
            // journal_entries.reference_number is unique per company, and the
            // same payment reference (a cheque/receipt number) could
            // legitimately be reused by the caller across different bills.
            // now()->format('YmdHis') alone can also collide when two
            // payments land in the same second, hence the random suffix.
            $journalReferenceNumber = 'SPMT-'.$bill->reference_number.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $journalEntry = $this->journalEntryService->postModuleEntry([
                'reference_number' => $journalReferenceNumber,
                'entry_date' => $data['payment_date'],
                'description' => "Payment to supplier for bill {$bill->reference_number}",
                'source_module' => 'procurement',
                'source_id' => $bill->id,
                'lines' => [
                    ['account_id' => $payableAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Accounts payable settled'],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash paid to supplier'],
                ],
            ]);

            return SupplierPayment::create([
                'supplier_id' => $data['supplier_id'],
                'supplier_bill_id' => $bill->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'paid_by' => Auth::id(),
                'journal_entry_id' => $journalEntry->id,
            ]);
        });
    }

    public function update(SupplierPayment $payment, array $data): SupplierPayment
    {
        $payment->update(collect($data)->only('reference_number')->toArray());

        return $payment;
    }

    public function delete(SupplierPayment $payment): void
    {
        throw ValidationException::withMessages([
            'payment' => ['A posted supplier payment cannot be deleted; it has already been recorded against the bill and the general ledger.'],
        ]);
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
