<?php

use App\Models\UnitOfMeasure;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('performs CRUD on units of measure', function () {
    $response = $this->postJson('/api/v1/units-of-measure', [
        'name' => 'Kilogram',
        'abbreviation' => 'Kg',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Kilogram');
    $unitId = $response->json('data.id');

    $this->getJson('/api/v1/units-of-measure')->assertOk()->assertJsonCount(1, 'data');

    $this->putJson("/api/v1/units-of-measure/{$unitId}", ['abbreviation' => 'kg'])
        ->assertOk()
        ->assertJsonPath('data.abbreviation', 'kg');

    $this->deleteJson("/api/v1/units-of-measure/{$unitId}")->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseMissing('units_of_measure', ['id' => $unitId]);
});

it('blocks deleting a unit of measure still referenced by a product', function () {
    $unit = createUnitOfMeasure($this->company);
    createProduct($this->company, ['unit' => $unit]);

    $this->deleteJson("/api/v1/units-of-measure/{$unit->id}")->assertUnprocessable();
    $this->assertDatabaseHas('units_of_measure', ['id' => $unit->id]);
});

it('seeds the default unit of measure catalog, skipping names that already exist', function () {
    createUnitOfMeasure($this->company, ['name' => 'Piece', 'abbreviation' => 'custom-pc']);

    $response = $this->postJson('/api/v1/units-of-measure/seed');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Kilogram', 'Liter', 'Box', 'Hour');

    // The pre-existing "Piece" row was left alone, not duplicated or overwritten.
    expect(UnitOfMeasure::where('company_id', $this->company->id)->where('name', 'Piece')->count())->toBe(1);
    expect(UnitOfMeasure::where('company_id', $this->company->id)->where('name', 'Piece')->value('abbreviation'))->toBe('custom-pc');

    // Calling it again is a no-op — no duplicate rows.
    $countAfterFirstSeed = UnitOfMeasure::where('company_id', $this->company->id)->count();
    $this->postJson('/api/v1/units-of-measure/seed')->assertOk();
    expect(UnitOfMeasure::where('company_id', $this->company->id)->count())->toBe($countAfterFirstSeed);
});

it('denies units-of-measure access to a user without the permission', function () {
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($cashier, ['*']);

    $this->postJson('/api/v1/units-of-measure/seed')->assertForbidden();
    $this->postJson('/api/v1/units-of-measure', ['name' => 'Box', 'abbreviation' => 'Box'])->assertForbidden();
});

it('scopes units of measure to the acting company', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $otherUnit = createUnitOfMeasure($otherCompany);

    // CompanyScope filters the route-model-binding query itself, so a cross-company id
    // 404s before the policy even runs — same pattern as every other TenantModel.
    $this->getJson("/api/v1/units-of-measure/{$otherUnit->id}")->assertNotFound();
});
