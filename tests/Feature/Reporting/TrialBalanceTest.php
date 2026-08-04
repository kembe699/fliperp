<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

it('produces a trial balance where total debits equal total credits and matches known per-account balances', function () {
    $response = $this->getJson('/api/v1/reports/trial-balance?from=2026-01-01&to=2026-01-31')->assertOk();

    $data = $response->json('data');

    expect((float) $data['total_debit'])->toBe(14400.0);
    expect((float) $data['total_credit'])->toBe(14400.0);
    expect((float) $data['total_debit'])->toBe((float) $data['total_credit']);

    $byCode = collect($data['accounts'])->keyBy('code');

    expect((float) $byCode['1000']['balance'])->toBe(300.0);   // Cash
    expect((float) $byCode['1200']['balance'])->toBe(4000.0);  // Inventory
    expect((float) $byCode['1100']['balance'])->toBe(0.0);     // AR: billed 1100, paid 1100, nets to zero
    expect((float) $byCode['2000']['balance'])->toBe(2000.0);  // Accounts Payable
    expect((float) $byCode['2100']['balance'])->toBe(100.0);   // Statutory Payable
    expect((float) $byCode['2200']['balance'])->toBe(900.0);   // Net Salaries Payable
    expect((float) $byCode['2300']['balance'])->toBe(300.0);   // Tax Payable
    expect((float) $byCode['4000']['balance'])->toBe(3000.0);  // Sales Revenue
    expect((float) $byCode['5000']['balance'])->toBe(1000.0);  // Salary Expense
    expect((float) $byCode['5300']['balance'])->toBe(1000.0);  // COGS
});

it('excludes journal entries outside the requested date range', function () {
    $response = $this->getJson('/api/v1/reports/trial-balance?from=2026-02-01&to=2026-02-28')->assertOk();

    expect((float) $response->json('data.total_debit'))->toBe(0.0);
    expect($response->json('data.accounts'))->toBe([]);
});

it('rejects a trial balance request where from is after to', function () {
    $this->getJson('/api/v1/reports/trial-balance?from=2026-01-31&to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
