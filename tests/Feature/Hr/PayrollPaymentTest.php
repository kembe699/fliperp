<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use Laravel\Sanctum\Sanctum;

// Salaries are paid employee by employee after a run is processed.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    seedChartOfAccounts($this->company);
    Sanctum::actingAs(createUserWithRole('company_admin', $this->company, $this->branch), ['*']);

    foreach ([500000, 300000] as $i => $basic) {
        $employee = createEmployee($this->company, $this->branch, ['employee_code' => 'PAY-'.$i]);
        SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => $basic, 'allowances' => [], 'effective_date' => now()->subMonth()->toDateString()]);
    }

    $this->runId = $this->postJson('/api/v1/payroll-runs', [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ])->json('data.id');
    $this->postJson("/api/v1/payroll-runs/{$this->runId}/process")->assertOk();
    $this->payslips = Payslip::where('payroll_run_id', $this->runId)->orderBy('net_pay', 'desc')->get();
});

it('pays one employee: the payslip is marked paid and cash leaves net salaries payable', function () {
    $slip = $this->payslips->first();

    $res = $this->postJson("/api/v1/payroll-runs/{$this->runId}/payslips/{$slip->id}/pay", ['reference' => 'MTN-123'])->assertOk();
    expect($res->json('data.paid_at'))->not->toBeNull()
        ->and($res->json('data.payment_reference'))->toBe('MTN-123')
        ->and($res->json('data.employee_name'))->not->toBeEmpty();

    $slip->refresh();
    $lines = JournalEntryLine::where('journal_entry_id', $slip->payment_journal_entry_id)->get()->keyBy(fn ($l) => ChartOfAccount::find($l->account_id)->code);
    expect((float) $lines['2200']->debit)->toBe((float) $slip->net_pay)
        ->and((float) $lines['1000']->credit)->toBe((float) $slip->net_pay);

    // Still one unpaid, so the run is not paid yet; paying the same person again is refused.
    $this->getJson("/api/v1/payroll-runs/{$this->runId}")->assertJsonPath('data.status', 'processed');
    $this->postJson("/api/v1/payroll-runs/{$this->runId}/payslips/{$slip->id}/pay")->assertStatus(422);
});

it('pays everyone left with Pay all, which makes the run paid', function () {
    $this->postJson("/api/v1/payroll-runs/{$this->runId}/payslips/{$this->payslips->first()->id}/pay")->assertOk();

    $this->postJson("/api/v1/payroll-runs/{$this->runId}/pay-all")->assertOk()->assertJsonPath('data.paid', 1);

    expect(Payslip::where('payroll_run_id', $this->runId)->whereNull('paid_at')->count())->toBe(0);
    $this->getJson("/api/v1/payroll-runs/{$this->runId}")->assertJsonPath('data.status', 'paid');
});

it('pays from a chosen bank account, not only cash', function () {
    $bank = ChartOfAccount::create(['company_id' => $this->company->id, 'code' => '1010', 'name' => 'Bank', 'type' => 'asset', 'is_active' => true]);
    $slip = $this->payslips->last();

    $this->postJson("/api/v1/payroll-runs/{$this->runId}/payslips/{$slip->id}/pay", ['account_id' => $bank->id])->assertOk();

    expect((float) JournalEntryLine::where('journal_entry_id', $slip->fresh()->payment_journal_entry_id)->where('account_id', $bank->id)->value('credit'))->toBe((float) $slip->net_pay);
});

it('downloads the run as an Excel file', function () {
    $res = $this->get("/api/v1/payroll-runs/{$this->runId}/export")->assertOk();

    expect($res->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($res->headers->get('content-disposition'))->toContain('.xlsx');
});
