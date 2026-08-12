<?php

use App\Models\ChartOfAccount;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('seeds the default chart of accounts, skipping codes that already exist', function () {
    ChartOfAccount::create([
        'company_id' => $this->company->id,
        'code' => '1000',
        'name' => 'Custom Cash Account',
        'type' => 'asset',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/chart-of-accounts/seed');

    $response->assertOk();
    $codes = collect($response->json('data'))->pluck('code');
    expect($codes)->toContain('1100', '2000', '4000', '5300');

    // The pre-existing "1000" row was left alone, not duplicated or overwritten.
    expect(ChartOfAccount::where('company_id', $this->company->id)->where('code', '1000')->count())->toBe(1);
    expect(ChartOfAccount::where('company_id', $this->company->id)->where('code', '1000')->value('name'))->toBe('Custom Cash Account');

    // Calling it again is a no-op — no duplicate rows.
    $countAfterFirstSeed = ChartOfAccount::where('company_id', $this->company->id)->count();
    $this->postJson('/api/v1/chart-of-accounts/seed')->assertOk();
    expect(ChartOfAccount::where('company_id', $this->company->id)->count())->toBe($countAfterFirstSeed);
});

it('denies seeding the chart of accounts to a user without the permission', function () {
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($cashier, ['*']);

    $this->postJson('/api/v1/chart-of-accounts/seed')->assertForbidden();
});

it('scopes a seeded chart of accounts to the acting company only', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany);
    Sanctum::actingAs($otherAdmin, ['*']);

    $this->postJson('/api/v1/chart-of-accounts/seed')->assertOk();

    expect(ChartOfAccount::where('company_id', $this->company->id)->count())->toBe(0);
    expect(ChartOfAccount::where('company_id', $otherCompany->id)->count())->toBeGreaterThan(0);
});
