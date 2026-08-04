<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

it('balances assets against liabilities plus equity for the known scenario', function () {
    $response = $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-01-31')->assertOk();

    $data = $response->json('data');

    expect((float) $data['assets']['total'])->toBe(4300.0);       // Cash 300 + Inventory 4000
    expect((float) $data['liabilities']['total'])->toBe(3300.0);  // AP 2000 + Statutory 100 + NetSalaries 900 + Tax 300
    expect((float) $data['equity']['net_income'])->toBe(1000.0);  // Revenue 3000 - Expenses 2000
    expect((float) $data['equity']['total'])->toBe(1000.0);       // no explicit equity accounts used
    expect((float) $data['total_liabilities_and_equity'])->toBe(4300.0);

    expect((float) $data['assets']['total'])->toBe((float) $data['total_liabilities_and_equity']);
});

it('excludes a payment dated after as_of from the snapshot', function () {
    // as_of 2026-01-16 is before the 2026-01-20 customer payment, so AR is
    // still outstanding and Cash hasn't received it yet.
    $response = $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-01-16')->assertOk();

    $data = $response->json('data');
    $assetsByCode = collect($data['assets']['accounts'])->keyBy('code');

    expect((float) $assetsByCode['1100']['amount'])->toBe(1100.0); // AR still outstanding
    expect((float) $assetsByCode['1000']['amount'])->toBe(-800.0); // Cash: 2200 in - 3000 out, before the 1100 customer payment
});

it('rejects a future as_of date unless explicitly allowed', function () {
    $future = now()->addYear()->toDateString();

    $this->getJson("/api/v1/reports/balance-sheet?as_of={$future}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->getJson("/api/v1/reports/balance-sheet?as_of={$future}&allow_future=1")
        ->assertOk();
});
