<?php

use App\Models\JournalEntryLine;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->paymentType = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->sentInvoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/invoices/{$this->sentInvoiceId}/send")->assertOk();
});

it('posts a balanced journal entry debiting cash and crediting receivable', function () {
    $response = $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 500,
    ])->assertCreated();

    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();
    $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'posted']);

    $lines = JournalEntryLine::where('journal_entry_id', $journalEntryId)->get();
    $cashLine = $lines->firstWhere('account_id', $this->accounts['1000']->id);
    $receivableLine = $lines->firstWhere('account_id', $this->accounts['1100']->id);

    expect((float) $cashLine->debit)->toBe(500.0);
    expect((float) $receivableLine->credit)->toBe(500.0);
});

it('recalculates invoice status to partially_paid then paid as payments accumulate', function () {
    $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 200,
    ])->assertCreated();

    $this->assertDatabaseHas('invoices', ['id' => $this->sentInvoiceId, 'status' => 'partially_paid', 'amount_paid' => 200]);

    $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 300,
    ])->assertCreated();

    $this->assertDatabaseHas('invoices', ['id' => $this->sentInvoiceId, 'status' => 'paid', 'amount_paid' => 500]);
});

it('rejects a payment that exceeds the outstanding invoice balance', function () {
    $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 501,
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('only allows updating the reference_number on an existing payment', function () {
    $paymentId = $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 100,
    ])->json('data.id');

    $this->putJson("/api/v1/customer-payments/{$paymentId}", ['reference_number' => 'REF-UPDATED'])
        ->assertOk()
        ->assertJsonPath('data.reference_number', 'REF-UPDATED');
});

it('never allows deleting a posted customer payment', function () {
    $paymentId = $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 100,
    ])->json('data.id');

    $this->deleteJson("/api/v1/customer-payments/{$paymentId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('computes a unified statement balance across POS sales and invoices/payments', function () {
    // A completed POS sale (debit, fully paid) alongside the invoice (debit)
    // and a partial payment (credit) above.
    $warehouse = createWarehouse($this->company, $this->branch);
    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 10,
    ]);

    $sale = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $warehouse->id,
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100],
        ],
    ])->json('data');

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
        'payment_type_id' => $this->paymentType->id,
        'amount' => $sale['total_amount'],
    ])->assertCreated();
    $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk();

    $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $this->sentInvoiceId,
        'payment_type_id' => $this->paymentType->id,
        'payment_date' => now()->toDateString(),
        'amount' => 300,
    ])->assertCreated();

    $response = $this->getJson("/api/v1/customers/{$this->customer->id}/statement")->assertOk();

    // Sale (200 debit, 200 credit) nets to zero; invoice (500 debit) less the
    // 300 payment (credit) leaves a 200 outstanding balance.
    expect((float) $response->json('data.closing_balance'))->toBe(200.0);
    expect(collect($response->json('data.transactions'))->pluck('type')->all())
        ->toBe(['sale', 'sale_payment', 'invoice', 'invoice_payment']);
});
