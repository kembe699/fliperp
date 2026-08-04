<?php

use App\Models\JournalEntryLine;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->supplier = createSupplier($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    $this->billId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'PAY-BILL-001',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 800,
    ])->json('data.id');
});

it('posts a balanced journal entry debiting accounts payable and crediting cash', function () {
    $response = $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $this->billId,
        'payment_date' => now()->toDateString(),
        'amount' => 800,
        'reference_number' => 'PAYREF-001',
    ]);

    $response->assertCreated();
    $journalEntryId = $response->json('data.journal_entry_id');

    expect($journalEntryId)->not->toBeNull();
    $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'posted']);

    $lines = JournalEntryLine::where('journal_entry_id', $journalEntryId)->get();
    $payableLine = $lines->firstWhere('account_id', $this->accounts['2000']->id);
    $cashLine = $lines->firstWhere('account_id', $this->accounts['1000']->id);

    expect((float) $payableLine->debit)->toBe(800.0);
    expect((float) $cashLine->credit)->toBe(800.0);
});

it('atomically updates the bill amount_paid when a payment is recorded', function () {
    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $this->billId,
        'payment_date' => now()->toDateString(),
        'amount' => 300,
    ])->assertCreated();

    $this->assertDatabaseHas('supplier_bills', ['id' => $this->billId, 'amount_paid' => 300]);
});

it('only allows updating the reference_number on an existing payment', function () {
    $paymentId = $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $this->billId,
        'payment_date' => now()->toDateString(),
        'amount' => 200,
    ])->json('data.id');

    $this->putJson("/api/v1/supplier-payments/{$paymentId}", ['reference_number' => 'UPDATED-REF'])
        ->assertOk()
        ->assertJsonPath('data.reference_number', 'UPDATED-REF');
});

it('filters the payment index by supplier_bill_id so a bill\'s payment history can be fetched', function () {
    $otherBillId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'PAY-BILL-002',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 500,
    ])->json('data.id');

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $this->billId,
        'payment_date' => now()->toDateString(),
        'amount' => 300,
    ])->assertCreated();

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $otherBillId,
        'payment_date' => now()->toDateString(),
        'amount' => 150,
    ])->assertCreated();

    $this->getJson("/api/v1/supplier-payments?supplier_bill_id={$this->billId}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.supplier_bill_id', $this->billId)
        ->assertJsonPath('data.0.amount', 300);
});

it('never allows deleting a posted supplier payment', function () {
    $paymentId = $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $this->billId,
        'payment_date' => now()->toDateString(),
        'amount' => 100,
    ])->json('data.id');

    $this->deleteJson("/api/v1/supplier-payments/{$paymentId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
