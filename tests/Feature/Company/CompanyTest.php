<?php

use Laravel\Sanctum\Sanctum;

it('allows a super_admin to perform full CRUD on companies', function () {
    $superAdmin = createUserWithRole('super_admin');
    Sanctum::actingAs($superAdmin, ['*']);

    $create = $this->postJson('/api/v1/companies', [
        'name' => 'New Co',
        'slug' => 'new-co',
        'currency_code' => 'USD',
        'timezone' => 'UTC',
    ]);
    $create->assertCreated()->assertJsonPath('success', true);
    $companyId = $create->json('data.id');

    $this->getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    $this->getJson("/api/v1/companies/{$companyId}")
        ->assertOk()
        ->assertJsonPath('data.slug', 'new-co');

    $this->putJson("/api/v1/companies/{$companyId}", ['name' => 'New Co Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Co Renamed');

    $this->deleteJson("/api/v1/companies/{$companyId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('companies', ['id' => $companyId]);
});

it('lets a company_admin view and update only their own company, but not create or delete companies', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    [$otherCompany] = createCompanyWithMainBranch();
    $companyAdmin = createUserWithRole('company_admin', $company, $branch);
    Sanctum::actingAs($companyAdmin, ['*']);

    // Can view their own company, and the index is scoped to just it.
    $this->getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $company->id);

    $this->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $company->id);

    $this->putJson("/api/v1/companies/{$company->id}", ['name' => 'Renamed Co'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Co');

    // Cannot reach another company's record at all.
    $this->getJson("/api/v1/companies/{$otherCompany->id}")->assertStatus(403);
    $this->putJson("/api/v1/companies/{$otherCompany->id}", ['name' => 'Hijacked'])->assertStatus(403);

    // Still cannot create or delete companies — those remain super_admin-only.
    $this->postJson('/api/v1/companies', ['name' => 'X', 'slug' => 'x'])->assertStatus(403);
    $this->deleteJson("/api/v1/companies/{$company->id}")->assertStatus(403);
});
