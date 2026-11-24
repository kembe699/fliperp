<?php

use App\Models\ChartOfAccount;
use App\Models\CompanySystemAccount;
use App\Models\Grant;
use App\Models\Donor;
use App\Models\JournalEntryLine;
use App\Services\Finance\ChartReplacementService;
use App\Services\Finance\SystemAccountService;
use Laravel\Sanctum\Sanctum;

// Which account each automatic posting uses is a per-company setting.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    app(SystemAccountService::class)->ensureDefaults($this->company->id);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
});

function account($company, string $code, string $name, string $type, bool $postable = true): ChartOfAccount
{
    return ChartOfAccount::create(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true, 'is_postable' => $postable]);
}

it('lists every function with the account it uses, defaulting to the current codes', function () {
    $rows = collect($this->getJson('/api/v1/system-accounts')->assertOk()->json('data'))->keyBy('function');

    expect($rows)->toHaveCount(count(SystemAccountService::FUNCTIONS))
        ->and($rows['cash']['account']['code'])->toBe('1000')
        ->and($rows['accounts_payable']['account']['code'])->toBe('2000')
        ->and($rows['net_salaries_payable']['account']['code'])->toBe('2200');
});

it('posts to the accounts the company chose instead of the default codes', function () {
    $bank = account($this->company, '1121', 'Operating Bank - USD', 'asset');
    $payables = account($this->company, '2110', 'Trade Payables', 'liability');
    $this->putJson('/api/v1/system-accounts', ['accounts' => ['cash' => $bank->id, 'accounts_payable' => $payables->id]])->assertOk();

    $supplier = createSupplier($this->company);
    $bill = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $supplier->id, 'reference_number' => 'B-1', 'bill_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(), 'subtotal' => 400,
    ])->assertCreated()->json('data');
    $entryId = $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $supplier->id, 'supplier_bill_id' => $bill['id'], 'payment_date' => now()->toDateString(), 'amount' => 400,
    ])->assertCreated()->json('data.journal_entry_id');

    $lines = JournalEntryLine::where('journal_entry_id', $entryId)->get()->keyBy('account_id');
    expect((float) $lines[$payables->id]->debit)->toBe(400.0)
        ->and((float) $lines[$bank->id]->credit)->toBe(400.0)
        ->and($lines->has($this->accounts['1000']->id))->toBeFalse();
});

it('refuses a heading, an inactive account or another company\'s account as a system account', function () {
    $heading = account($this->company, '1100H', 'Cash and Cash Equivalents', 'asset', postable: false);
    [$other] = createCompanyWithMainBranch();
    $foreign = account($other, '1000', 'Their cash', 'asset');

    $this->putJson('/api/v1/system-accounts', ['accounts' => ['cash' => $heading->id]])->assertStatus(422)->assertJsonValidationErrors('accounts.cash');
    $this->putJson('/api/v1/system-accounts', ['accounts' => ['cash' => $foreign->id]])->assertStatus(422)->assertJsonValidationErrors('accounts.cash');
    $this->putJson('/api/v1/system-accounts', ['accounts' => ['nonsense' => $this->accounts['1000']->id]])->assertStatus(422);
});

it('never lets a journal line land on a heading', function () {
    $heading = account($this->company, '1001', 'Cash heading', 'asset', postable: false);

    $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-H', 'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $heading->id, 'debit' => 10, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 10],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('lines');
});

it('fails a posting clearly when its system account has become a heading', function () {
    CompanySystemAccount::where('company_id', $this->company->id)->where('function', 'cash')->delete();
    $this->accounts['1000']->update(['is_postable' => false]);

    expect(fn () => app(SystemAccountService::class)->account($this->company->id, 'cash'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('protects an account that a function is set to from being deleted or made a heading', function () {
    $this->deleteJson("/api/v1/chart-of-accounts/{$this->accounts['1000']->id}")->assertStatus(422);
    $this->putJson("/api/v1/chart-of-accounts/{$this->accounts['1000']->id}", ['code' => '1000', 'name' => 'Cash and Bank', 'type' => 'asset', 'is_postable' => false])->assertStatus(422);

    expect(ChartOfAccount::find($this->accounts['1000']->id)->is_postable)->toBeTrue();
});

it('maps a newly registered company to its default accounts', function () {
    $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Mapped NGO', 'admin_name' => 'Admin', 'admin_email' => 'admin@mapped.test',
        'admin_password' => 'Password123', 'admin_password_confirmation' => 'Password123',
    ])->assertCreated();
    $company = \App\Models\Company::where('name', 'Mapped NGO')->firstOrFail();

    expect(CompanySystemAccount::where('company_id', $company->id)->count())->toBeGreaterThanOrEqual(15)
        ->and(app(SystemAccountService::class)->account($company->id, 'cash')->code)->toBe('1000');
});

// ── Replacing a whole chart (G711) ─────────────────────────────────────

function smallChart(): array
{
    $row = fn ($code, $parent, $name, $type, $postable = true) => ['code' => $code, 'parent_code' => $parent, 'name' => $name, 'type' => $type, 'is_postable' => $postable, 'is_active' => true];

    return [
        'accounts' => [
            $row('1000', null, 'ASSETS', 'asset', false),
            $row('1121', '1000', 'Operating Bank - USD', 'asset'),
            $row('1210', '1000', 'Grants Receivable', 'asset'),
            $row('1430', '1000', 'Programme Supplies Inventory', 'asset'),
            $row('1595', '1000', 'Accumulated Depreciation', 'asset'),
            $row('2000', null, 'LIABILITIES', 'liability', false),
            $row('2110', '2000', 'Trade Payables', 'liability'),
            $row('2130', '2000', 'Payroll Payable', 'liability'),
            $row('2140', '2000', 'Withholding Tax Payable', 'liability'),
            $row('2150', '2000', 'Pension Payable', 'liability'),
            $row('3100', null, 'Unrestricted Fund', 'equity'),
            $row('4110', null, 'Grant Income', 'revenue'),
            $row('4420', null, 'FX Gain', 'revenue'),
            $row('5390', null, 'Programme Supplies', 'expense'),
            $row('6110', null, 'Salaries', 'expense'),
            $row('6710', null, 'Depreciation', 'expense'),
            $row('6900', null, 'Other Costs', 'expense'),
            $row('7210', null, 'FX Loss', 'expense'),
        ],
        'system_accounts' => [
            'cash' => '1121', 'accounts_receivable' => '1210', 'inventory' => '1430', 'accumulated_depreciation' => '1595',
            'accounts_payable' => '2110', 'statutory_deductions_payable' => '2150', 'net_salaries_payable' => '2130', 'tax_payable' => '2140',
            'opening_balance_equity' => '3100', 'sales_revenue' => '4110', 'salary_expense' => '6110', 'depreciation_expense' => '6710',
            'default_expense' => '6900', 'cost_of_sales' => '5390', 'cash_short_over' => '6900', 'fx_gain' => '4420', 'fx_loss' => '7210',
        ],
        'replace_old_accounts' => ['1000' => '1121'],
    ];
}

it('replaces a chart: dry run changes nothing, apply swaps every account and moves what used the old ones', function () {
    $donor = Donor::create(['company_id' => $this->company->id, 'name' => 'Donor']);
    $grant = Grant::create(['company_id' => $this->company->id, 'donor_id' => $donor->id, 'code' => 'G-1', 'name' => 'G', 'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'status' => 'active']);
    $line = $grant->budgetLines()->create(['account_id' => $this->accounts['1000']->id, 'budgeted_amount' => 1000]);
    [$other] = createCompanyWithMainBranch();
    $otherAccounts = seedChartOfAccounts($other);
    $service = app(ChartReplacementService::class);

    $dry = $service->replace($this->company, smallChart(), apply: false);
    expect($dry['applied'])->toBeFalse()
        ->and(ChartOfAccount::where('company_id', $this->company->id)->pluck('code')->sort()->values()->all())->toBe(collect($this->accounts)->keys()->map(fn ($c) => (string) $c)->sort()->values()->all());

    $result = $service->replace($this->company, smallChart(), apply: true);

    $codes = ChartOfAccount::where('company_id', $this->company->id)->pluck('is_postable', 'code');
    expect($result['accounts_created'])->toBe(18)
        ->and($codes)->toHaveCount(18)
        ->and($codes['1000'])->toBeFalse()
        ->and($codes['1121'])->toBeTrue()
        ->and(ChartOfAccount::find($line->fresh()->account_id)->code)->toBe('1121')
        ->and(app(SystemAccountService::class)->account($this->company->id, 'cash')->code)->toBe('1121')
        ->and(ChartOfAccount::where('company_id', $this->company->id)->where('code', '1121')->value('parent_id'))
        ->toBe(ChartOfAccount::where('company_id', $this->company->id)->where('code', '1000')->value('id'))
        // Another company's chart is untouched.
        ->and(ChartOfAccount::withoutGlobalScopes()->where('company_id', $other->id)->count())->toBe(count($otherAccounts));
});

it('refuses to replace the chart of a company that has already posted', function () {
    $entry = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-1', 'entry_date' => now()->toDateString(),
        'lines' => [['account_id' => $this->accounts['1000']->id, 'debit' => 5, 'credit' => 0], ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 5]],
    ])->assertCreated();

    expect(fn () => app(ChartReplacementService::class)->replace($this->company, smallChart(), apply: true))->toThrow(RuntimeException::class);
    expect(ChartOfAccount::where('company_id', $this->company->id)->where('code', '1121')->exists())->toBeFalse();
});

it('ships a complete, valid chart for G711', function () {
    $chart = json_decode(file_get_contents(base_path('database/data/g711_chart_of_accounts.json')), true);
    $rows = collect($chart['accounts'])->keyBy('code');

    expect($chart['accounts'])->toHaveCount(226)
        ->and(array_keys($chart['system_accounts']))->toEqualCanonicalizing(array_keys(SystemAccountService::FUNCTIONS));
    foreach ($chart['system_accounts'] as $code) {
        expect($rows[$code]['is_postable'])->toBeTrue()->and($rows[$code]['is_active'])->toBeTrue();
    }
});
