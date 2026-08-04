<?php

use Laravel\Sanctum\Sanctum;

it('lists available currencies with code, name and symbol', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $admin = createUserWithRole('company_admin', $company, $branch);
    Sanctum::actingAs($admin, ['*']);

    $response = $this->getJson('/api/v1/currencies')->assertOk();

    $byCode = collect($response->json('data'))->keyBy('code');

    expect($byCode->has('SSP'))->toBeTrue();
    expect($byCode->has('UGX'))->toBeTrue();
    expect($byCode->has('USD'))->toBeTrue();
    expect($byCode['USD']['symbol'])->toBe('$');
    expect($byCode['SSP']['name'])->toBe('South Sudanese Pound');
});

it('allows an unauthenticated request for currencies, since the public receipt verification page needs it', function () {
    $this->getJson('/api/v1/currencies')->assertOk();
});
