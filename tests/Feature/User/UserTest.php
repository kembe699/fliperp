<?php

use Laravel\Sanctum\Sanctum;

it('performs CRUD on users scoped to company with role and branch assignment', function () {
    [$company, $mainBranch] = createCompanyWithMainBranch();
    $companyAdmin = createUserWithRole('company_admin', $company, $mainBranch);
    Sanctum::actingAs($companyAdmin, ['*']);

    $create = $this->postJson('/api/v1/users', [
        'name' => 'New Cashier',
        'email' => 'cashier.new@demo.test',
        'password' => 'Password123',
        'branch_id' => $mainBranch->id,
        'roles' => ['cashier'],
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.branch_id', $mainBranch->id)
        ->assertJsonPath('data.roles.0', 'cashier');

    $userId = $create->json('data.id');
    $this->assertDatabaseHas('users', ['id' => $userId, 'company_id' => $company->id]);

    $this->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/v1/users/{$userId}")
        ->assertOk()
        ->assertJsonPath('data.email', 'cashier.new@demo.test');

    $this->putJson("/api/v1/users/{$userId}", ['roles' => ['accountant']])
        ->assertOk()
        ->assertJsonPath('data.roles.0', 'accountant');

    $this->deleteJson("/api/v1/users/{$userId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $userId]);
});

it('denies access to users belonging to another company', function () {
    [$companyA, $branchA] = createCompanyWithMainBranch();
    [$companyB, $branchB] = createCompanyWithMainBranch();

    $adminA = createUserWithRole('company_admin', $companyA, $branchA);
    $userB = createUserWithRole('cashier', $companyB, $branchB);

    Sanctum::actingAs($adminA, ['*']);

    $this->getJson("/api/v1/users/{$userB->id}")->assertStatus(404);
});
