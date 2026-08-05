<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    // A restricted CRM staff member: general CRM access via direct
    // permissions only (deliberately no role, so nothing grants
    // crm-customers.view-all), to exercise the assignment-based scoping
    // without needing a dedicated "support-tier" role in the seeder.
    $this->restrictedStaff = User::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $this->restrictedStaff->givePermissionTo([
        'crm-customer-services.view',
        'crm-activities.view',
        'crm-account-assignments.view',
    ]);

    $this->assignedCustomer = createCustomer($this->company, ['name' => 'Assigned Co']);
    $this->unassignedCustomer = createCustomer($this->company, ['name' => 'Unassigned Co']);
    $this->service = createCrmService($this->company);

    Sanctum::actingAs($this->admin, ['*']);
    $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->assignedCustomer->id,
        'user_id' => $this->restrictedStaff->id,
        'role' => 'support',
    ])->assertCreated();
});

it('lets a view-all user see every customer in the CRM list', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $names = collect($this->getJson('/api/v1/crm/customers')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Assigned Co')->toContain('Unassigned Co');
});

it('restricts a non-view-all user to only their assigned customers in the CRM list', function () {
    Sanctum::actingAs($this->restrictedStaff, ['*']);

    $names = collect($this->getJson('/api/v1/crm/customers')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Assigned Co')->not->toContain('Unassigned Co');
});

it('allows a restricted user to view an assigned customer but 403s on an unassigned one', function () {
    Sanctum::actingAs($this->restrictedStaff, ['*']);

    $this->getJson("/api/v1/crm/customers/{$this->assignedCustomer->id}")->assertOk();
    $this->getJson("/api/v1/crm/customers/{$this->unassignedCustomer->id}")->assertStatus(403);
});

it('restricts the service statement endpoint the same way', function () {
    Sanctum::actingAs($this->restrictedStaff, ['*']);

    $this->getJson("/api/v1/crm/customers/{$this->assignedCustomer->id}/service-statement")->assertOk();
    $this->getJson("/api/v1/crm/customers/{$this->unassignedCustomer->id}/service-statement")->assertStatus(403);
});

it('restricts customer_id-filtered activities the same way', function () {
    Sanctum::actingAs($this->admin, ['*']);
    $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->assignedCustomer->id,
        'type' => 'note',
        'subject' => 'Visible note',
        'activity_date' => now()->toDateString(),
    ])->assertCreated();
    $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->unassignedCustomer->id,
        'type' => 'note',
        'subject' => 'Hidden note',
        'activity_date' => now()->toDateString(),
    ])->assertCreated();

    Sanctum::actingAs($this->restrictedStaff, ['*']);

    $this->getJson("/api/v1/crm/activities?customer_id={$this->assignedCustomer->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/crm/activities?customer_id={$this->unassignedCustomer->id}")
        ->assertStatus(403);
});

it('updates visibility immediately when a customer is assigned and then unassigned', function () {
    Sanctum::actingAs($this->restrictedStaff, ['*']);
    $this->getJson("/api/v1/crm/customers/{$this->unassignedCustomer->id}")->assertStatus(403);

    Sanctum::actingAs($this->admin, ['*']);
    $assignment = $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->unassignedCustomer->id,
        'user_id' => $this->restrictedStaff->id,
        'role' => 'support',
    ])->json('data');

    Sanctum::actingAs($this->restrictedStaff, ['*']);
    $this->getJson("/api/v1/crm/customers/{$this->unassignedCustomer->id}")->assertOk();

    Sanctum::actingAs($this->admin, ['*']);
    $this->postJson("/api/v1/crm/account-assignments/{$assignment['id']}/unassign")->assertOk();

    Sanctum::actingAs($this->restrictedStaff, ['*']);
    $this->getJson("/api/v1/crm/customers/{$this->unassignedCustomer->id}")->assertStatus(403);
});

it('returns 404 not 403 for a customer belonging to another company entirely', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $otherCustomer = createCustomer($otherCompany);

    Sanctum::actingAs($this->admin, ['*']);
    $this->getJson("/api/v1/crm/customers/{$otherCustomer->id}")->assertStatus(404);
});
