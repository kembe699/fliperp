<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->supplier = createSupplier($this->company);

    Sanctum::actingAs($this->admin, ['*']);
});

it('calculates a correct running balance across multiple bills and payments', function () {
    $bill1Id = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'STMT-BILL-1',
        'bill_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'subtotal' => 1000,
    ])->json('data.id');

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $bill1Id,
        'payment_date' => '2026-01-02',
        'amount' => 400,
    ])->assertCreated();

    $bill2Id = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'STMT-BILL-2',
        'bill_date' => '2026-01-03',
        'due_date' => '2026-02-02',
        'subtotal' => 500,
    ])->json('data.id');

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $bill2Id,
        'payment_date' => '2026-01-04',
        'amount' => 300,
    ])->assertCreated();

    $response = $this->getJson("/api/v1/suppliers/{$this->supplier->id}/statement");

    $response->assertOk();
    $data = $response->json('data');

    expect($data['transactions'])->toHaveCount(4);
    expect((float) $data['closing_balance'])->toBe(800.0);

    $balances = collect($data['transactions'])->pluck('running_balance')->map(fn ($value) => (float) $value)->all();
    expect($balances)->toBe([1000.0, 600.0, 1100.0, 800.0]);
});

it('prevents deleting a supplier that has bills', function () {
    $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GUARD-BILL-1',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 100,
    ])->assertCreated();

    $this->deleteJson("/api/v1/suppliers/{$this->supplier->id}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('allows deleting a supplier with no downstream records', function () {
    $this->deleteJson("/api/v1/suppliers/{$this->supplier->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('suppliers', ['id' => $this->supplier->id]);
});
