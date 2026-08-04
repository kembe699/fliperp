<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('performs CRUD on statutory deduction rules', function () {
    $response = $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'NSSF',
        'type' => 'pension',
        'calculation_type' => 'percentage',
        'country_code' => 'UGA',
        'config' => ['rate' => 0.05],
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'NSSF');
    $id = $response->json('data.id');

    $this->getJson('/api/v1/statutory-deduction-rules')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/statutory-deduction-rules/{$id}")->assertOk();

    $this->putJson("/api/v1/statutory-deduction-rules/{$id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/api/v1/statutory-deduction-rules/{$id}")->assertOk()->assertJsonPath('success', true);
});

it('accepts a valid percentage config and rejects one missing rate', function () {
    $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'Flat Tax',
        'type' => 'tax',
        'calculation_type' => 'percentage',
        'country_code' => 'UGA',
        'config' => ['rate' => 0.1],
    ])->assertCreated();

    $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'Broken Tax',
        'type' => 'tax',
        'calculation_type' => 'percentage',
        'country_code' => 'UGA',
        'config' => ['amount' => 5000],
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('accepts a valid fixed config and rejects one missing amount', function () {
    $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'Flat Fee',
        'type' => 'other',
        'calculation_type' => 'fixed',
        'country_code' => 'UGA',
        'config' => ['amount' => 15000],
    ])->assertCreated();

    $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'Broken Fee',
        'type' => 'other',
        'calculation_type' => 'fixed',
        'country_code' => 'UGA',
        'config' => ['rate' => 0.1],
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('accepts a valid bracket config and rejects one with malformed brackets', function () {
    $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'PAYE',
        'type' => 'tax',
        'calculation_type' => 'bracket',
        'country_code' => 'UGA',
        'config' => [
            'brackets' => [
                ['min' => 0, 'max' => 235000, 'rate' => 0],
                ['min' => 235000, 'max' => null, 'rate' => 0.3],
            ],
        ],
    ])->assertCreated();

    $this->postJson('/api/v1/statutory-deduction-rules', [
        'name' => 'Broken PAYE',
        'type' => 'tax',
        'calculation_type' => 'bracket',
        'country_code' => 'UGA',
        'config' => [
            'brackets' => [
                ['min' => 0, 'rate' => 'not-a-number'],
            ],
        ],
    ])->assertStatus(422)->assertJsonPath('success', false);
});
