<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

it('groups cash movement by activity and totals correctly', function () {
    $response = $this->getJson('/api/v1/reports/cash-flow?from=2026-01-01&to=2026-01-31')->assertOk();

    $data = $response->json('data');
    $byModule = collect($data['categories'])->keyBy('source_module');

    expect((float) $byModule['sales']['cash_in'])->toBe(2200.0);
    expect((float) $byModule['sales']['cash_out'])->toBe(0.0);
    expect((float) $byModule['sales']['net'])->toBe(2200.0);

    expect((float) $byModule['supplier_payments']['cash_in'])->toBe(0.0);
    expect((float) $byModule['supplier_payments']['cash_out'])->toBe(3000.0);
    expect((float) $byModule['supplier_payments']['net'])->toBe(-3000.0);

    expect((float) $byModule['customer_payments']['cash_in'])->toBe(1100.0);
    expect((float) $byModule['customer_payments']['net'])->toBe(1100.0);

    expect((float) $byModule['payroll_runs']['cash_in'])->toBe(0.0);
    expect((float) $byModule['payroll_runs']['cash_out'])->toBe(0.0);
    expect((float) $byModule['payroll_runs']['net'])->toBe(0.0);

    expect((float) $data['net_cash_movement'])->toBe(300.0);
});
