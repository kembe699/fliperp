<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('creates and lists services in the catalog', function () {
    $this->postJson('/api/v1/crm/services', [
        'name' => 'Website Design',
        'category' => 'Design',
        'default_price' => 1500,
    ])->assertCreated()->assertJsonPath('data.name', 'Website Design');

    $this->getJson('/api/v1/crm/services')->assertOk()->assertJsonCount(1, 'data');
});

it('updates and deletes a service', function () {
    $service = createCrmService($this->company, ['name' => 'Old Name']);

    $this->putJson("/api/v1/crm/services/{$service->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->deleteJson("/api/v1/crm/services/{$service->id}")->assertOk();
    expect(App\Models\CrmService::find($service->id))->toBeNull();
});

it('returns 404 for a service belonging to another company', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $otherService = createCrmService($otherCompany);

    $this->getJson("/api/v1/crm/services/{$otherService->id}")->assertStatus(404);
});
