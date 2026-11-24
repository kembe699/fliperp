<?php

use App\Models\AssetCategory;
use App\Models\ExchangeRate;
use App\Models\JournalEntry;
use App\Models\PaymentApprovalRule;
use App\Models\SalaryStructure;
use App\Models\Payslip;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
 * Every automatic posting the ERP makes, for one demo company on the default
 * chart: supplier bill and payment, salary run and salary payment, sales
 * invoice with tax and its receipt, POS sale with tax and cost of sales, a
 * cash drawer shortage, a payment request, foreign-exchange gain and loss on
 * settlement, depreciation and an opening balance.
 *
 * The journal lines are compared with a snapshot recorded on the code as it
 * was before system accounts became a per-company setting
 * (tests/Feature/Finance/snapshots/system_account_postings.json). Run with
 * WRITE_POSTING_SNAPSHOT=1 to record it again.
 */

const POSTING_SNAPSHOT = __DIR__.'/snapshots/system_account_postings.json';

afterEach(fn () => Carbon::setTestNow());

function runEveryPosting($test): void
{
    Notification::fake();
    Carbon::setTestNow('2026-03-15 10:00:00');
    [$company, $branch] = createCompanyWithMainBranch(['allow_self_approval' => true]);
    $test->company = $company;
    $test->branch = $branch;
    $accounts = seedChartOfAccounts($company);
    $admin = createUserWithRole('company_admin', $company, $branch);
    Sanctum::actingAs($admin, ['*']);
    ExchangeRate::create(['company_id' => $company->id, 'currency_code' => 'SSP', 'rate' => 7800, 'effective_date' => '2026-03-01']);
    ExchangeRate::create(['company_id' => $company->id, 'currency_code' => 'SSP', 'rate' => 8000, 'effective_date' => '2026-03-10']);

    // Supplier bill with tax, then paid in full.
    $supplier = createSupplier($company);
    $bill = $test->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $supplier->id, 'reference_number' => 'BILL-1', 'bill_date' => '2026-03-15', 'due_date' => '2026-04-15',
        'subtotal' => 500, 'tax_amount' => 50,
    ])->assertCreated()->json('data');
    $test->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $supplier->id, 'supplier_bill_id' => $bill['id'], 'payment_date' => '2026-03-15', 'amount' => 550, 'reference_number' => 'SP-1',
    ])->assertCreated();

    // Salary run with a 5% pension deduction, and one salary paid.
    $test->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'Pension', 'type' => 'pension', 'calculation_type' => 'percentage', 'country_code' => 'SSD', 'config' => ['rate' => 0.05],
    ])->assertCreated();
    $employee = createEmployee($company, $branch, ['employee_code' => 'EMP-1']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 500000, 'allowances' => [], 'effective_date' => '2026-01-01']);
    $runId = $test->postJson('/api/v1/payroll-runs', ['period_start' => '2026-03-01', 'period_end' => '2026-03-31'])->json('data.id');
    $test->postJson("/api/v1/payroll-runs/{$runId}/process")->assertOk();
    $slip = Payslip::where('payroll_run_id', $runId)->firstOrFail();
    $test->postJson("/api/v1/payroll-runs/{$runId}/payslips/{$slip->id}/pay", ['reference' => 'PAY-1'])->assertOk();

    // Sales invoice with tax, then received.
    $customer = createCustomer($company);
    $taxRate = createTaxRate($company, ['rate' => 10]);
    $product = createProduct($company, ['cost_price' => 50, 'selling_price' => 100]);
    $paymentType = createPaymentType($company);
    $invoice = $test->postJson('/api/v1/invoices', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-03-15', 'due_date' => '2026-04-15',
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100, 'tax_rate_id' => $taxRate->id]],
    ])->assertCreated()->json('data');
    $test->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk();
    $test->postJson('/api/v1/customer-payments', [
        'customer_id' => $customer->id, 'invoice_id' => $invoice['id'], 'payment_type_id' => $paymentType->id, 'payment_date' => '2026-03-15', 'amount' => 440,
    ])->assertCreated();

    // POS cash sale with tax and cost of sales.
    $warehouse = createWarehouse($company, $branch);
    app(StockMovementService::class)->record(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'purchase', 'quantity' => 50]);
    $sale = $test->postJson('/api/v1/sales', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'tax_rate_id' => $taxRate->id]],
    ])->json('data');
    $test->postJson("/api/v1/sales/{$sale['id']}/payments", ['payment_type_id' => $paymentType->id, 'amount' => 220])->assertCreated();
    $test->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk();

    // Cash drawer closed 300 short.
    $test->postJson('/api/v1/cash-drawer/open', ['branch_id' => $branch->id, 'opening_float' => 1000])->assertCreated();
    $test->postJson('/api/v1/cash-drawer/close', ['closing_float' => 700])->assertOk();

    // Payment request with no expense account chosen, approved and paid.
    $roleId = \App\Models\Role::where('name', 'company_admin')->value('id');
    PaymentApprovalRule::create(['company_id' => $company->id, 'name' => 'All', 'min_amount' => 0, 'max_amount' => null])
        ->steps()->create(['label' => 'Admin', 'role_id' => $roleId, 'sequence' => 1]);
    $pr = $test->postJson('/api/v1/payment-requests', [
        'request_date' => '2026-03-15', 'payee_name' => 'Office Rentals', 'description' => 'Rent', 'amount' => 120, 'currency_code' => 'USD',
    ])->assertCreated()->json('data');
    $approver = createUserWithRole('company_admin', $company, $branch);
    $test->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    Sanctum::actingAs($approver, ['*']);
    $test->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
    $test->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-03-15', 'reference' => 'PR-PAY-1'])->assertOk();
    Sanctum::actingAs($admin, ['*']);

    // SSP bill paid after the rate moved (gain), SSP invoice paid after it moved (loss).
    $sspBill = $test->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $supplier->id, 'reference_number' => 'SSP-BILL-1', 'bill_date' => '2026-03-05', 'due_date' => '2026-04-05',
        'subtotal' => 80000, 'currency_code' => 'SSP',
    ])->assertCreated()->json('data');
    $test->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $supplier->id, 'supplier_bill_id' => $sspBill['id'], 'payment_date' => '2026-03-12', 'amount' => 80000,
    ])->assertCreated();
    $sspInvoice = $test->postJson('/api/v1/invoices', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-03-05', 'due_date' => '2026-04-05', 'currency_code' => 'SSP',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 78000]],
    ])->assertCreated()->json('data');
    $test->postJson("/api/v1/invoices/{$sspInvoice['id']}/send")->assertOk();
    $test->postJson('/api/v1/customer-payments', [
        'customer_id' => $customer->id, 'invoice_id' => $sspInvoice['id'], 'payment_type_id' => $paymentType->id, 'payment_date' => '2026-03-12', 'amount' => 78000,
    ])->assertCreated();

    // Depreciation.
    $category = AssetCategory::create(['company_id' => $company->id, 'name' => 'Equipment', 'depreciation_method' => 'straight_line', 'useful_life_years' => 10]);
    $test->postJson('/api/v1/assets', [
        'branch_id' => $branch->id, 'asset_category_id' => $category->id, 'asset_code' => 'AST-1', 'name' => 'Generator',
        'purchase_date' => '2025-03-15', 'purchase_cost' => 120000,
    ])->assertCreated();
    $test->postJson('/api/v1/assets/run-depreciation')->assertOk();

    // Opening balance on the bank account.
    $test->putJson("/api/v1/chart-of-accounts/{$accounts['1000']->id}", [
        'code' => '1000', 'name' => 'Cash and Bank', 'type' => 'asset', 'opening_balance' => 2500, 'opening_balance_date' => '2026-01-01',
    ])->assertOk();
}

/** Every journal line of the company, in posting order, by account code. */
function postedLines(int $companyId): array
{
    return JournalEntry::withoutGlobalScopes()->with('lines.account')
        ->whereIn('id', \App\Models\JournalEntryLine::query()
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('chart_of_accounts.company_id', $companyId)->select('journal_entry_lines.journal_entry_id'))
        ->orderBy('id')->get()
        ->map(fn ($entry) => [
            'source' => $entry->source_module,
            'status' => $entry->status,
            'currency' => $entry->currency_code,
            'lines' => $entry->lines->sortBy('id')->values()->map(fn ($line) => [
                'account' => $line->account->code.' '.$line->account->name,
                'debit' => round((float) $line->debit, 2),
                'credit' => round((float) $line->credit, 2),
                'transaction_debit' => round((float) $line->transaction_debit, 2),
                'transaction_credit' => round((float) $line->transaction_credit, 2),
            ])->all(),
        ])->all();
}

it('posts every automatic journal line exactly as before system accounts became a setting', function () {
    runEveryPosting($this);
    $lines = postedLines($this->company->id);

    if (getenv('WRITE_POSTING_SNAPSHOT')) {
        @mkdir(dirname(POSTING_SNAPSHOT), 0775, true);
        file_put_contents(POSTING_SNAPSHOT, json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)."\n");
    }

    expect(count($lines))->toBeGreaterThan(10)
        ->and($lines)->toBe(json_decode(file_get_contents(POSTING_SNAPSHOT), true));
});
