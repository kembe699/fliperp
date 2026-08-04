<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->employee = createEmployee($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('performs CRUD on salary structures', function () {
    $response = $this->postJson('/api/v1/salary-structures', [
        'employee_id' => $this->employee->id,
        'basic_salary' => 1000000,
        'allowances' => ['transport' => 100000],
        'effective_date' => '2026-01-01',
    ]);

    $response->assertCreated()->assertJsonPath('data.basic_salary', 1000000);
    $id = $response->json('data.id');

    $this->getJson('/api/v1/salary-structures')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/salary-structures/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("/api/v1/salary-structures/{$id}", ['basic_salary' => 1100000])
        ->assertOk()
        ->assertJsonPath('data.basic_salary', 1100000);

    $this->deleteJson("/api/v1/salary-structures/{$id}")->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseMissing('salary_structures', ['id' => $id]);
});

it('rejects a salary structure whose effective_date overlaps an existing one for the same employee', function () {
    $this->postJson('/api/v1/salary-structures', [
        'employee_id' => $this->employee->id,
        'basic_salary' => 1000000,
        'effective_date' => '2026-01-01',
    ])->assertCreated();

    $this->postJson('/api/v1/salary-structures', [
        'employee_id' => $this->employee->id,
        'basic_salary' => 1200000,
        'effective_date' => '2026-01-01',
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('allows two different employees to share the same effective_date', function () {
    $otherEmployee = createEmployee($this->company, $this->branch, ['employee_code' => 'EMP-OTHER']);

    $this->postJson('/api/v1/salary-structures', [
        'employee_id' => $this->employee->id,
        'basic_salary' => 1000000,
        'effective_date' => '2026-01-01',
    ])->assertCreated();

    $this->postJson('/api/v1/salary-structures', [
        'employee_id' => $otherEmployee->id,
        'basic_salary' => 900000,
        'effective_date' => '2026-01-01',
    ])->assertCreated();
});
