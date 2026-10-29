<?php

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\MeProject;
use App\Models\SupplierBill;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->supplier = createSupplier($this->company);
    Sanctum::actingAs($this->admin, ['*']);
});

function accountBalance(int $companyId, string $code): float
{
    $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    $totals = $account->hasMany(\App\Models\JournalEntryLine::class, 'account_id')
        ->whereHas('journalEntry', fn ($query) => $query->whereIn('status', JournalEntry::LEDGER_STATUSES))
        ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')->first();

    return round((float) $totals->d - (float) $totals->c, 2);
}

function createBill($test, array $overrides = []): array
{
    return $test->postJson('/api/v1/supplier-bills', array_merge([
        'supplier_id' => $test->supplier->id,
        'reference_number' => 'BILL-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 500,
        'tax_amount' => 50,
    ], $overrides))->assertCreated()->json('data');
}

// ── Chart of accounts on company creation ──────────────────────────────

it('gives a newly registered company the default chart of accounts', function () {
    $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'New NGO',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@new-ngo.test',
        'admin_password' => 'Password123',
        'admin_password_confirmation' => 'Password123',
    ])->assertCreated();

    $company = Company::where('name', 'New NGO')->firstOrFail();
    $codes = ChartOfAccount::withoutGlobalScopes()->where('company_id', $company->id)->pluck('code')->all();

    expect($codes)->toContain('1000', '1100', '2000', '4000', '5200', '5500');
});

// ── Cash flow ──────────────────────────────────────────────────────────

it('counts every cash movement in the cash flow, including manual journals', function () {
    $entryId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-CASH-1',
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 300, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 300],
        ],
    ])->json('data.id');
    Sanctum::actingAs(createUserWithRole('company_admin', $this->company, $this->branch), ['*']);
    $this->postJson("/api/v1/journal-entries/{$entryId}/post")->assertOk();

    $data = $this->getJson('/api/v1/reports/cash-flow?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->json('data');

    expect(collect($data['categories'])->firstWhere('source_module', 'other')['cash_in'])->toEqual(300)
        ->and($data['net_cash_movement'])->toEqual(300);
});

// ── Supplier bills post to Accounts Payable ────────────────────────────

it('posts a manually entered bill as expense and accounts payable', function () {
    $bill = createBill($this);

    expect($bill['journal_entry_id'])->not->toBeNull()
        ->and($bill['expense_account_id'])->toBe($this->accounts['5200']->id)
        ->and(accountBalance($this->company->id, '2000'))->toBe(-550.0)
        ->and(accountBalance($this->company->id, '5200'))->toBe(550.0);
});

it('expenses a bill to the account chosen on it', function () {
    createBill($this, ['expense_account_id' => $this->accounts['5000']->id]);

    expect(accountBalance($this->company->id, '5000'))->toBe(550.0);
});

it('re-posts a bill when its amount changes, and reverses it when deleted', function () {
    $bill = createBill($this);

    $this->putJson("/api/v1/supplier-bills/{$bill['id']}", ['subtotal' => 700, 'tax_amount' => 0])->assertOk();
    expect(accountBalance($this->company->id, '2000'))->toBe(-700.0);

    $this->deleteJson("/api/v1/supplier-bills/{$bill['id']}")->assertOk();
    expect(accountBalance($this->company->id, '2000'))->toBe(0.0)
        ->and(accountBalance($this->company->id, '5200'))->toBe(0.0);
});

it('leaves accounts payable at zero once a bill is paid in full', function () {
    $bill = createBill($this);

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $bill['id'],
        'payment_date' => now()->toDateString(),
        'amount' => 550,
    ])->assertCreated();

    expect(accountBalance($this->company->id, '2000'))->toBe(0.0);
});

it('catches up bills entered before bills posted, with a dry run first', function () {
    $bill = SupplierBill::create([
        'company_id' => $this->company->id, 'supplier_id' => $this->supplier->id, 'reference_number' => 'OLD-1',
        'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 400, 'tax_amount' => 0, 'total_amount' => 400, 'status' => 'unpaid',
        'currency_code' => 'USD', 'exchange_rate' => 1,
    ]);

    $this->artisan('finance:post-unposted-supplier-bills')->assertSuccessful();
    expect($bill->fresh()->journal_entry_id)->toBeNull();

    $this->artisan('finance:post-unposted-supplier-bills --apply')->assertSuccessful();
    expect($bill->fresh()->journal_entry_id)->not->toBeNull()
        ->and(accountBalance($this->company->id, '2000'))->toBe(-400.0);
});

// ── Receivables and payables ───────────────────────────────────────────

it('reports payables per supplier, aged, and reconciled to the control account', function () {
    createBill($this, ['bill_date' => now()->subDays(80)->toDateString(), 'due_date' => now()->subDays(45)->toDateString(), 'subtotal' => 200, 'tax_amount' => 0]);
    createBill($this, ['subtotal' => 300, 'tax_amount' => 0]);

    $data = $this->getJson('/api/v1/reports/payables')->assertOk()->json('data');

    expect($data['totals']['outstanding'])->toEqual(500)
        ->and($data['totals']['d31_60'])->toEqual(200)
        ->and($data['totals']['d0_30'])->toEqual(300)
        ->and($data['totals']['overdue'])->toEqual(200)
        ->and($data['control_account']['balance'])->toEqual(500)
        ->and($data['difference'])->toEqual(0)
        ->and($data['suppliers'][0]['outstanding'])->toEqual(500)
        ->and($data['documents'])->toHaveCount(2);
});

it('reports receivables as at a date, counting only payments made by then', function () {
    $customer = createCustomer($this->company);
    $product = createProduct($this->company, ['selling_price' => 100]);
    $invoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => $customer->id,
        'invoice_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->addDays(20)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100]],
    ])->json('data.id');
    $this->postJson("/api/v1/invoices/{$invoiceId}/send")->assertOk();
    $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $customer->id, 'invoice_id' => $invoiceId, 'payment_type_id' => createPaymentType($this->company)->id,
        'payment_date' => now()->toDateString(), 'amount' => 150,
    ])->assertCreated();

    $today = $this->getJson('/api/v1/reports/receivables')->json('data');
    expect($today['totals']['outstanding'])->toEqual(250)
        ->and($today['totals']['not_yet_due'])->toEqual(250)
        ->and($today['difference'])->toEqual(0)
        ->and($today['customers'][0]['name'])->toBe($customer->name);

    // Before the payment, the whole invoice was owed.
    $before = $this->getJson('/api/v1/reports/receivables?as_of='.now()->subDay()->toDateString())->json('data');
    expect($before['totals']['outstanding'])->toEqual(400);
});

// ── M&E connected to the budget and the ledger ─────────────────────────

it('shows a project its budget against actual spending from its budget period', function () {
    $period = BudgetPeriod::create([
        'company_id' => $this->company->id, 'name' => 'FY26',
        'start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->endOfYear()->toDateString(), 'status' => 'active',
    ]);
    BudgetLine::create(['budget_period_id' => $period->id, 'account_id' => $this->accounts['5200']->id, 'budgeted_amount' => 1000]);
    $project = MeProject::create([
        'company_id' => $this->company->id, 'name' => 'Water project', 'start_date' => now()->startOfYear()->toDateString(),
        'status' => 'ongoing', 'budget_period_id' => $period->id,
    ]);
    createBill($this, ['subtotal' => 250, 'tax_amount' => 0]);

    $finance = $this->getJson("/api/v1/me-projects/{$project->id}/dashboard")->assertOk()->json('data.finance');

    expect($finance['budgeted_total'])->toEqual(1000)
        ->and($finance['actual_total'])->toEqual(250)
        ->and($finance['variance'])->toEqual(750)
        ->and($finance['utilization_percent'])->toEqual(25);
});

// ── Reversals ──────────────────────────────────────────────────────────

it('nets a reversed entry to zero in the reports instead of showing its opposite', function () {
    $entryId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-REV-RPT',
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 120, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 120],
        ],
    ])->json('data.id');
    Sanctum::actingAs(createUserWithRole('company_admin', $this->company, $this->branch), ['*']);
    $this->postJson("/api/v1/journal-entries/{$entryId}/post")->assertOk();
    $this->postJson("/api/v1/journal-entries/{$entryId}/reverse")->assertCreated();

    $from = now()->startOfMonth()->toDateString();
    $to = now()->toDateString();
    $cash = collect($this->getJson("/api/v1/reports/trial-balance?from={$from}&to={$to}")->json('data.accounts'))->firstWhere('code', '1000');
    $profit = $this->getJson("/api/v1/reports/profit-and-loss?from={$from}&to={$to}")->json('data.net_profit');

    expect($cash['balance'])->toEqual(0)->and($profit)->toEqual(0);
});
