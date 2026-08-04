<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->supplier = createSupplier($this->company);

    Sanctum::actingAs($this->admin, ['*']);
});

it('creates a manual supplier bill for services without a GRN', function () {
    $response = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'SVC-BILL-001',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 500,
        'tax_amount' => 50,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.total_amount', 550)
        ->assertJsonPath('data.status', 'unpaid');
});

it('recalculates bill status from unpaid to partially_paid to paid as payments are applied', function () {
    $billId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'SVC-BILL-002',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000,
    ])->json('data.id');

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $billId,
        'payment_date' => now()->toDateString(),
        'amount' => 400,
    ])->assertCreated();

    $this->getJson("/api/v1/supplier-bills/{$billId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'partially_paid')
        ->assertJsonPath('data.amount_paid', 400);

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $billId,
        'payment_date' => now()->toDateString(),
        'amount' => 600,
    ])->assertCreated();

    $this->getJson("/api/v1/supplier-bills/{$billId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.balance_due', 0);
});

it('detects an overdue bill against its due_date once unpaid past that date', function () {
    $billId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'SVC-BILL-OVERDUE',
        'bill_date' => now()->subDays(45)->toDateString(),
        'due_date' => now()->subDays(15)->toDateString(),
        'subtotal' => 300,
    ])->json('data.id');

    $this->getJson("/api/v1/supplier-bills/{$billId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'overdue');
});

it('rejects a payment that exceeds the outstanding balance', function () {
    $billId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'SVC-BILL-OVERPAY',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 100,
    ])->json('data.id');

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $billId,
        'payment_date' => now()->toDateString(),
        'amount' => 150,
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('filters the bill index by status, supplier, date range, and search', function () {
    $otherSupplier = createSupplier($this->company, ['name' => 'Other Supplier']);

    $paid = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'FILTER-PAID',
        'bill_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->addDays(20)->toDateString(),
        'subtotal' => 100,
    ])->json('data.id');
    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $paid,
        'payment_date' => now()->toDateString(),
        'amount' => 100,
    ])->assertCreated();

    $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $otherSupplier->id,
        'reference_number' => 'FILTER-OTHER-SUPPLIER',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 200,
    ])->assertCreated();

    $this->getJson('/api/v1/supplier-bills?status=paid')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', 'FILTER-PAID');

    $this->getJson("/api/v1/supplier-bills?supplier_id={$otherSupplier->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', 'FILTER-OTHER-SUPPLIER');

    $this->getJson('/api/v1/supplier-bills?search=other-supplier')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', 'FILTER-OTHER-SUPPLIER');

    $this->getJson('/api/v1/supplier-bills?search='.urlencode($otherSupplier->name))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', 'FILTER-OTHER-SUPPLIER');

    $this->getJson('/api/v1/supplier-bills?from='.now()->subDays(15)->toDateString().'&to='.now()->subDays(5)->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', 'FILTER-PAID');
});

it('blocks deleting a bill that already has a payment applied', function () {
    $billId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'SVC-BILL-DELETE',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 200,
    ])->json('data.id');

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $billId,
        'payment_date' => now()->toDateString(),
        'amount' => 50,
    ])->assertCreated();

    $this->deleteJson("/api/v1/supplier-bills/{$billId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
