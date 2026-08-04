<?php

use App\Models\JournalEntryLine;
use App\Models\SalaryStructure;
use App\Models\StatutoryDeductionRule;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    $this->employee = createEmployee($this->company, $this->branch);

    SalaryStructure::create([
        'employee_id' => $this->employee->id,
        'basic_salary' => 1000000,
        'allowances' => ['transport' => 100000],
        'effective_date' => now()->subMonth()->toDateString(),
    ]);

    StatutoryDeductionRule::create([
        'company_id' => $this->company->id,
        'name' => 'Flat Tax',
        'type' => 'tax',
        'calculation_type' => 'percentage',
        'config' => ['rate' => 0.10],
        'country_code' => 'UGA',
        'is_active' => true,
    ]);
});

it('calculates salary, generates a payslip and posts a balanced journal entry', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $payrollRunId = $this->postJson('/api/v1/payroll-runs', [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ])->json('data.id');

    $response = $this->postJson("/api/v1/payroll-runs/{$payrollRunId}/process");

    $response->assertOk()->assertJsonPath('data.status', 'processed');

    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();

    $this->assertDatabaseHas('payslips', [
        'payroll_run_id' => $payrollRunId,
        'employee_id' => $this->employee->id,
        'gross_pay' => 1100000,
        'total_deductions' => 110000,
        'net_pay' => 990000,
    ]);

    $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'posted']);

    $totalDebit = JournalEntryLine::where('journal_entry_id', $journalEntryId)->sum('debit');
    $totalCredit = JournalEntryLine::where('journal_entry_id', $journalEntryId)->sum('credit');
    expect((float) $totalDebit)->toBe((float) $totalCredit);
    expect((float) $totalDebit)->toBe(1100000.0);

    $payslips = $this->getJson("/api/v1/payroll-runs/{$payrollRunId}/payslips");
    $payslips->assertOk()->assertJsonCount(1, 'data');
});

it('denies payroll processing for roles without the permission', function () {
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);

    $payrollRunId = $this->postJson('/api/v1/payroll-runs', [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ])->json('data.id');

    Sanctum::actingAs($cashier, ['*']);

    $this->postJson("/api/v1/payroll-runs/{$payrollRunId}/process")->assertStatus(403);
});
