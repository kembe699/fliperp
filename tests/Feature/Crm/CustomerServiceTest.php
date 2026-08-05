<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->service = createCrmService($this->company, ['name' => 'Hosting', 'default_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('logs a customer service entry against a customer', function () {
    $response = $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id,
        'crm_service_id' => $this->service->id,
        'price_charged' => 100,
        'start_date' => now()->subMonths(2)->toDateString(),
    ])->assertCreated();

    expect($response->json('data.status'))->toBe('active');
});

it('calculates a running total in date order on the service statement', function () {
    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id,
        'crm_service_id' => $this->service->id,
        'price_charged' => 100,
        'start_date' => now()->subMonths(3)->toDateString(),
    ])->assertCreated();

    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id,
        'crm_service_id' => $this->service->id,
        'price_charged' => 250,
        'start_date' => now()->subMonths(2)->toDateString(),
    ])->assertCreated();

    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id,
        'crm_service_id' => $this->service->id,
        'price_charged' => 50,
        'start_date' => now()->subMonth()->toDateString(),
    ])->assertCreated();

    $statement = $this->getJson("/api/v1/crm/customers/{$this->customer->id}/service-statement")
        ->assertOk()
        ->json('data');

    expect($statement['total_spent'])->toEqual(400.0);
    expect(collect($statement['entries'])->pluck('running_total')->all())->toEqual([100.0, 350.0, 400.0]);
});

it('scopes the service statement to only the requested customer', function () {
    $otherCustomer = createCustomer($this->company, ['name' => 'Someone Else']);

    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id,
        'crm_service_id' => $this->service->id,
        'price_charged' => 100,
        'start_date' => now()->toDateString(),
    ])->assertCreated();

    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $otherCustomer->id,
        'crm_service_id' => $this->service->id,
        'price_charged' => 999,
        'start_date' => now()->toDateString(),
    ])->assertCreated();

    $statement = $this->getJson("/api/v1/crm/customers/{$this->customer->id}/service-statement")->json('data');

    expect($statement['total_spent'])->toEqual(100.0);
});
