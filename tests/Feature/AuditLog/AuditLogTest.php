<?php

use Laravel\Sanctum\Sanctum;

it('records audit log entries for create, update and delete actions', function () {
    [$company, $mainBranch] = createCompanyWithMainBranch();
    $companyAdmin = createUserWithRole('company_admin', $company, $mainBranch);
    Sanctum::actingAs($companyAdmin, ['*']);

    $branchId = $this->postJson('/api/v1/branches', [
        'name' => 'Audit Branch',
        'code' => 'AUD',
    ])->json('data.id');

    $this->assertDatabaseHas('audit_logs', [
        'module' => 'Branch',
        'action' => 'created',
        'record_id' => $branchId,
        'company_id' => $company->id,
    ]);

    $this->putJson("/api/v1/branches/{$branchId}", ['name' => 'Audit Branch Renamed']);

    $this->assertDatabaseHas('audit_logs', [
        'module' => 'Branch',
        'action' => 'updated',
        'record_id' => $branchId,
    ]);

    $this->deleteJson("/api/v1/branches/{$branchId}");

    $this->assertDatabaseHas('audit_logs', [
        'module' => 'Branch',
        'action' => 'deleted',
        'record_id' => $branchId,
    ]);
});

it('lists audit logs paginated and filterable by module and date range', function () {
    [$company, $mainBranch] = createCompanyWithMainBranch();
    $companyAdmin = createUserWithRole('company_admin', $company, $mainBranch);
    Sanctum::actingAs($companyAdmin, ['*']);

    $this->postJson('/api/v1/branches', ['name' => 'Branch One', 'code' => 'ONE']);
    $this->postJson('/api/v1/branches', ['name' => 'Branch Two', 'code' => 'TWO']);

    $response = $this->getJson('/api/v1/audit-logs?module=Branch&date_from='.now()->toDateString());

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    expect(collect($response->json('data'))->every(fn ($entry) => $entry['module'] === 'Branch'))->toBeTrue();
});
