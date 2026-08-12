<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->product = createProduct($this->company, ['name' => 'Router Installation Kit', 'selling_price' => 89.5]);
});

it('materializes a shadow CRM service for a product the first time it is picked in a CRM context', function () {
    $user = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson("/api/v1/products/{$this->product->id}/ensure-crm-service");

    $response->assertOk();
    expect($response->json('data.name'))->toBe('Router Installation Kit');
    expect((float) $response->json('data.default_price'))->toBe(89.5);

    $this->assertDatabaseHas('crm_services', [
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'name' => 'Router Installation Kit',
    ]);
});

it('returns the same shadow service on repeated calls instead of creating duplicates', function () {
    $user = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($user, ['*']);

    $first = $this->postJson("/api/v1/products/{$this->product->id}/ensure-crm-service")->json('data.id');
    $second = $this->postJson("/api/v1/products/{$this->product->id}/ensure-crm-service")->json('data.id');

    expect($second)->toBe($first);
    expect(\App\Models\CrmService::where('product_id', $this->product->id)->count())->toBe(1);
});

it('forbids resolving a product to a CRM service for a user without crm-services access', function () {
    $user = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/products/{$this->product->id}/ensure-crm-service")->assertForbidden();
});
