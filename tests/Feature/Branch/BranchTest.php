<?php

use Laravel\Sanctum\Sanctum;

it('performs CRUD on branches scoped to the authenticated user company', function () {
    [$company, $mainBranch] = createCompanyWithMainBranch();
    $companyAdmin = createUserWithRole('company_admin', $company, $mainBranch);
    Sanctum::actingAs($companyAdmin, ['*']);

    $create = $this->postJson('/api/v1/branches', [
        'name' => 'Second Branch',
        'code' => 'SEC',
    ]);
    $create->assertCreated()->assertJsonPath('data.company_id', $company->id);
    $branchId = $create->json('data.id');

    $this->assertDatabaseHas('branches', ['id' => $branchId, 'company_id' => $company->id]);

    $this->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/v1/branches/{$branchId}")
        ->assertOk()
        ->assertJsonPath('data.code', 'SEC');

    $this->putJson("/api/v1/branches/{$branchId}", ['name' => 'Second Branch Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Second Branch Renamed');

    $this->deleteJson("/api/v1/branches/{$branchId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('branches', ['id' => $branchId]);
});

it('denies cross-company access to another company branch', function () {
    [$companyA, $branchA] = createCompanyWithMainBranch();
    [$companyB, $branchB] = createCompanyWithMainBranch();

    $adminA = createUserWithRole('company_admin', $companyA, $branchA);
    Sanctum::actingAs($adminA, ['*']);

    $this->getJson("/api/v1/branches/{$branchB->id}")->assertStatus(404);
    $this->putJson("/api/v1/branches/{$branchB->id}", ['name' => 'Hijacked'])->assertStatus(404);
    $this->deleteJson("/api/v1/branches/{$branchB->id}")->assertStatus(404);

    $this->assertDatabaseHas('branches', ['id' => $branchB->id, 'name' => $branchB->name]);
});
