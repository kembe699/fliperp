<?php

use App\Models\CrmService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->service = createCrmService($this->company, ['name' => 'Onsite Support', 'default_price' => 175]);
});

it('materializes a shadow product for a CRM service the first time it is added as a line item', function () {
    $user = createUserWithRole('branch_manager', $this->company, $this->branch);
    Sanctum::actingAs($user, ['*']);

    expect($this->service->product_id)->toBeNull();

    $response = $this->postJson("/api/v1/crm/services/{$this->service->id}/ensure-product");

    $response->assertOk();
    expect($response->json('data.name'))->toBe('Onsite Support');
    expect((float) $response->json('data.selling_price'))->toBe(175.0);
    expect($response->json('data.track_inventory'))->toBeFalse();

    $this->service->refresh();
    expect($this->service->product_id)->not->toBeNull();
    expect($this->service->product_id)->toBe($response->json('data.id'));
});

it('returns the same shadow product on repeated calls instead of creating duplicates', function () {
    $user = createUserWithRole('branch_manager', $this->company, $this->branch);
    Sanctum::actingAs($user, ['*']);

    $first = $this->postJson("/api/v1/crm/services/{$this->service->id}/ensure-product")->json('data.id');
    $second = $this->postJson("/api/v1/crm/services/{$this->service->id}/ensure-product")->json('data.id');

    expect($second)->toBe($first);
});

it('forbids resolving a service to a product for a user without crm-services access', function () {
    $user = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/crm/services/{$this->service->id}/ensure-product")->assertForbidden();
});
