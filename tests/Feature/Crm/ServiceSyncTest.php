<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);

    $this->serviceA = createCrmService($this->company, ['name' => 'Consulting']);
    $this->serviceB = createCrmService($this->company, ['name' => 'Installation']);
    $this->serviceC = createCrmService($this->company, ['name' => 'Support Plan']);

    $this->stage = createCrmPipelineStage($this->company, ['name' => 'New Lead', 'position' => 1]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Jane Lead'])->json('data');
    $this->deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Test Deal',
        'value' => 500,
    ])->json('data');
});

it('attaches interested services to a lead', function () {
    $response = $this->postJson("/api/v1/crm/leads/{$this->lead['id']}/services", [
        'service_ids' => [$this->serviceA->id, $this->serviceB->id],
    ]);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe([$this->serviceA->id, $this->serviceB->id]);
});

it('replaces the full set of interested lead services on resync, not merging', function () {
    $this->postJson("/api/v1/crm/leads/{$this->lead['id']}/services", [
        'service_ids' => [$this->serviceA->id, $this->serviceB->id],
    ])->assertOk();

    $response = $this->postJson("/api/v1/crm/leads/{$this->lead['id']}/services", [
        'service_ids' => [$this->serviceC->id],
    ]);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$this->serviceC->id]);
});

it('attaches services to a deal', function () {
    $response = $this->postJson("/api/v1/crm/deals/{$this->deal['id']}/services", [
        'service_ids' => [$this->serviceA->id, $this->serviceC->id],
    ]);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe([$this->serviceA->id, $this->serviceC->id]);
});

it('replaces the full set of deal services on resync, not merging', function () {
    $this->postJson("/api/v1/crm/deals/{$this->deal['id']}/services", [
        'service_ids' => [$this->serviceA->id, $this->serviceB->id, $this->serviceC->id],
    ])->assertOk();

    $response = $this->postJson("/api/v1/crm/deals/{$this->deal['id']}/services", [
        'service_ids' => [$this->serviceB->id],
    ]);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$this->serviceB->id]);
});

it('can clear all interested services by syncing an empty array', function () {
    $this->postJson("/api/v1/crm/leads/{$this->lead['id']}/services", [
        'service_ids' => [$this->serviceA->id],
    ])->assertOk();

    $response = $this->postJson("/api/v1/crm/leads/{$this->lead['id']}/services", [
        'service_ids' => [],
    ]);

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

it('rejects a service id belonging to another company', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $foreignService = createCrmService($otherCompany);

    $this->postJson("/api/v1/crm/leads/{$this->lead['id']}/services", [
        'service_ids' => [$foreignService->id],
    ])->assertStatus(422);
});
