<?php

use App\Models\AccountingPeriod;
use Laravel\Sanctum\Sanctum;

// Reports can be filtered by accounting period, so report viewers may list the periods (own company only).
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    AccountingPeriod::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'Q1 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'status' => 'open']);
    [$other] = createCompanyWithMainBranch();
    AccountingPeriod::withoutGlobalScopes()->create(['company_id' => $other->id, 'name' => 'Theirs', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
});

it('lets a user who can view reports list their own company\'s periods', function () {
    $user = createUserWithRole('cashier', $this->company, $this->branch);
    expect($user->can('accounting-periods.view'))->toBeFalse();
    $user->givePermissionTo('reports.view');
    Sanctum::actingAs($user, ['*']);

    $names = collect($this->getJson('/api/v1/accounting-periods?per_page=100')->assertOk()->json('data'))->pluck('name');

    expect($names->all())->toBe(['Q1 2026']);
});

it('still refuses users who can neither view reports nor periods', function () {
    $user = createUserWithRole('cashier', $this->company, $this->branch);
    expect($user->can('reports.view'))->toBeFalse();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounting-periods')->assertForbidden();
});

it('does not let report viewers create or close periods', function () {
    $user = createUserWithRole('cashier', $this->company, $this->branch);
    $user->givePermissionTo('reports.view');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/accounting-periods', ['name' => 'X', 'start_date' => '2026-04-01', 'end_date' => '2026-04-30'])->assertForbidden();
});
