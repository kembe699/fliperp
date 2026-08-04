<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

it('computes correct revenue, COGS, gross profit and net profit for the known scenario', function () {
    $response = $this->getJson('/api/v1/reports/profit-and-loss?from=2026-01-01&to=2026-01-31')->assertOk();

    $data = $response->json('data');

    expect((float) $data['revenue']['total'])->toBe(3000.0); // 2000 POS + 1000 invoiced
    expect((float) $data['cogs']['total'])->toBe(1000.0);
    expect((float) $data['gross_profit'])->toBe(2000.0);
    expect((float) $data['operating_expenses']['total'])->toBe(1000.0); // salary expense only
    expect((float) $data['net_profit'])->toBe(1000.0);

    $cogsAccounts = collect($data['cogs']['accounts'])->keyBy('code');
    expect((float) $cogsAccounts['5300']['amount'])->toBe(1000.0);

    $opexAccounts = collect($data['operating_expenses']['accounts'])->keyBy('code');
    expect($opexAccounts->has('5300'))->toBeFalse(); // COGS excluded from operating expenses
    expect((float) $opexAccounts['5000']['amount'])->toBe(1000.0);
});

it('rejects a profit and loss request where from is after to', function () {
    $this->getJson('/api/v1/reports/profit-and-loss?from=2026-01-31&to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
