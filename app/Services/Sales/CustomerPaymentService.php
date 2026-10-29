<?php

namespace App\Services\Sales;

use App\Models\ChartOfAccount;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Services\Finance\ExchangeRateService;
use App\Services\Finance\JournalEntryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPaymentService
{
    public const ACCOUNTS_RECEIVABLE_ACCOUNT_CODE = '1100';

    public const CASH_ACCOUNT_CODE = '1000';

    public function __construct(
        protected InvoiceService $invoiceService,
        protected JournalEntryService $journalEntryService,
        protected ExchangeRateService $exchangeRateService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CustomerPayment::query()->latest('payment_date')->paginate($perPage);
    }

    public function create(array $data): CustomerPayment
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);

            $amount = round((float) $data['amount'], 2);
            $balanceDue = round((float) $invoice->total_amount - (float) $invoice->amount_paid, 2);

            if ($amount > $balanceDue) {
                throw ValidationException::withMessages([
                    'amount' => ["Payment amount exceeds the outstanding balance of {$balanceDue}."],
                ]);
            }

            $invoice->update(['amount_paid' => (float) $invoice->amount_paid + $amount]);
            $this->invoiceService->refreshStatus($invoice);

            $receivableAccount = $this->resolveAccount($invoice->company_id, self::ACCOUNTS_RECEIVABLE_ACCOUNT_CODE);
            $cashAccount = $this->resolveAccount($invoice->company_id, self::CASH_ACCOUNT_CODE);

            // Paid in the invoice's currency, at the rate for the payment date
            // (or one typed on the payment). Receivable is relieved at the
            // invoice's own rate — what it was booked at — and cash comes in
            // at today's; the base difference is the realised FX gain/loss.
            [$currency, $paymentRate] = $this->exchangeRateService->resolve(
                $invoice->company_id, $invoice->currency_code, $data['payment_date'], $data['exchange_rate'] ?? null,
            );
            $cashBase = round($amount / $paymentRate, 2);
            $receivableBase = round($amount / (float) $invoice->exchange_rate, 2);
            $fxLine = $this->exchangeRateService->realisedFxLine($invoice->company_id, $cashBase - $receivableBase);

            $journalReferenceNumber = 'CPMT-'.$invoice->reference_number.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $journalEntry = $this->journalEntryService->postModuleEntry([
                'reference_number' => $journalReferenceNumber,
                'entry_date' => $data['payment_date'],
                'description' => "Payment received from customer for invoice {$invoice->reference_number}",
                'source_module' => 'sales',
                'source_id' => $invoice->id,
                'currency_code' => $currency,
                'exchange_rate' => $paymentRate,
                'lines' => array_values(array_filter([
                    ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0, 'base_debit' => $cashBase, 'description' => 'Cash received from customer'],
                    ['account_id' => $receivableAccount->id, 'debit' => 0, 'credit' => $amount, 'base_credit' => $receivableBase, 'description' => 'Accounts receivable settled'],
                    $fxLine,
                ])),
            ]);

            return CustomerPayment::create([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $invoice->id,
                'payment_type_id' => $data['payment_type_id'],
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'currency_code' => $currency,
                'exchange_rate' => $paymentRate,
                'reference_number' => $data['reference_number'] ?? null,
                'received_by' => Auth::id(),
                'journal_entry_id' => $journalEntry->id,
            ]);
        });
    }

    public function update(CustomerPayment $payment, array $data): CustomerPayment
    {
        $payment->update(collect($data)->only('reference_number')->toArray());

        return $payment;
    }

    public function delete(CustomerPayment $payment): void
    {
        throw ValidationException::withMessages([
            'payment' => ['A posted customer payment cannot be deleted; it has already been recorded against the invoice and the general ledger.'],
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
