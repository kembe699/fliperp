<?php

use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);

    Permission::firstOrCreate(['name' => 'products.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'products.create', 'guard_name' => 'web']);
});

it('creates a role with an initial permission set', function () {
    $response = $this->postJson('/api/v1/roles', [
        'name' => 'warehouse_clerk',
        'permissions' => ['products.view'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'warehouse_clerk')
        ->assertJsonPath('data.permissions.0', 'products.view');
});

it('replaces the full permission set on update, dropping anything not in the new list', function () {
    $roleId = $this->postJson('/api/v1/roles', [
        'name' => 'warehouse_clerk',
        'permissions' => ['products.view', 'products.create'],
    ])->json('data.id');

    $this->putJson("/api/v1/roles/{$roleId}", ['permissions' => ['products.view']])
        ->assertOk()
        ->assertJsonCount(1, 'data.permissions')
        ->assertJsonPath('data.permissions.0', 'products.view');
});

it('reports how many users currently hold each role', function () {
    $roleId = $this->postJson('/api/v1/roles', ['name' => 'warehouse_clerk'])->json('data.id');

    $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonFragment(['name' => 'warehouse_clerk', 'users_count' => 0]);

    createUserWithRole('warehouse_clerk', $this->company, $this->branch);

    $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonFragment(['name' => 'warehouse_clerk', 'users_count' => 1]);
});

it('blocks deleting a role that users currently hold, with a clear message naming the count', function () {
    $roleId = $this->postJson('/api/v1/roles', ['name' => 'warehouse_clerk'])->json('data.id');
    createUserWithRole('warehouse_clerk', $this->company, $this->branch);
    createUserWithRole('warehouse_clerk', $this->company, $this->branch);

    $response = $this->deleteJson("/api/v1/roles/{$roleId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($response->json('errors.role.0'))->toContain('2 users');

    $this->assertDatabaseHas('roles', ['id' => $roleId]);
});

it('allows deleting a role that no user holds', function () {
    $roleId = $this->postJson('/api/v1/roles', ['name' => 'warehouse_clerk'])->json('data.id');

    $this->deleteJson("/api/v1/roles/{$roleId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('roles', ['id' => $roleId]);
});

it('rejects creating a role with a duplicate name', function () {
    Role::create(['name' => 'duplicate_role', 'guard_name' => 'web']);

    $this->postJson('/api/v1/roles', ['name' => 'duplicate_role'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('scopes a company-created custom role away from other companies entirely', function () {
    $roleId = $this->postJson('/api/v1/roles', ['name' => 'warehouse_supervisor'])->json('data.id');

    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);

    // Not visible in the other company's role list.
    $this->getJson('/api/v1/roles')->assertOk()->assertJsonMissing(['name' => 'warehouse_supervisor']);

    // Not viewable, editable, or deletable directly by id either.
    $this->getJson("/api/v1/roles/{$roleId}")->assertStatus(422);
    $this->putJson("/api/v1/roles/{$roleId}", ['name' => 'hijacked'])->assertStatus(422);
    $this->deleteJson("/api/v1/roles/{$roleId}")->assertStatus(422);
    $this->assertDatabaseHas('roles', ['id' => $roleId, 'name' => 'warehouse_supervisor']);
});

it('rejects assigning a role that belongs to a different company', function () {
    $roleId = $this->postJson('/api/v1/roles', ['name' => 'warehouse_supervisor_2'])->json('data.id');
    $roleName = \App\Models\Role::find($roleId)->name;

    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);

    $this->postJson('/api/v1/users', [
        'name' => 'Someone',
        'email' => 'someone@other-company.test',
        'password' => 'password123',
        'roles' => [$roleName],
    ])->assertStatus(422);
});

it('blocks a company_admin from renaming or deleting a shared system role', function () {
    $cashierRole = \App\Models\Role::where('name', 'cashier')->firstOrFail();

    $this->putJson("/api/v1/roles/{$cashierRole->id}", ['name' => 'renamed_cashier'])->assertStatus(422);
    $this->deleteJson("/api/v1/roles/{$cashierRole->id}")->assertStatus(422);
    $this->assertDatabaseHas('roles', ['id' => $cashierRole->id, 'name' => 'cashier']);
});
