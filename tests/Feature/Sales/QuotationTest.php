<?php

use App\Models\JournalEntry;
use App\Models\StockMovement;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createDraftQuotation = function (string $reference = 'QUO-TEST-001') {
        return $this->postJson('/api/v1/quotations', [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'reference_number' => $reference,
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100],
            ],
        ])->json('data');
    };
});

it('enforces the draft -> sent -> accepted status transition rules', function () {
    $quotation = ($this->createDraftQuotation)();

    // Cannot accept/reject before it has been sent.
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertStatus(422);

    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk()->assertJsonPath('data.status', 'sent');

    // Cannot send again once sent.
    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertStatus(422);

    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
});

it('allows rejecting a sent quotation', function () {
    $quotation = ($this->createDraftQuotation)();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();

    $this->postJson("/api/v1/quotations/{$quotation['id']}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
});

it('allows editing a sent quotation, but locks it once the customer has responded', function () {
    $quotation = ($this->createDraftQuotation)();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();

    // Revising after sending is normal practice (a corrected price, a line the
    // customer asked for) — and the edit must not knock it back to draft.
    $this->putJson("/api/v1/quotations/{$quotation['id']}", ['notes' => 'changed'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'changed')
        ->assertJsonPath('data.status', 'sent');

    // Deleting still requires draft, so a sent quotation can't vanish on the customer.
    $this->deleteJson("/api/v1/quotations/{$quotation['id']}")->assertStatus(422);

    // Once accepted the figures back a decision (and soon an invoice) — locked.
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk();
    $this->putJson("/api/v1/quotations/{$quotation['id']}", ['notes' => 'too late'])->assertStatus(422);
});

it('only converts an accepted quotation, copying items 1:1 and linking both records', function () {
    $quotation = ($this->createDraftQuotation)();

    $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice")->assertStatus(422);

    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk();

    $response = $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice")->assertCreated();

    expect($response->json('data.quotation_id'))->toBe($quotation['id']);
    expect($response->json('data.status'))->toBe('draft');
    expect(count($response->json('data.items')))->toBe(1);
    expect((float) $response->json('data.items.0.quantity'))->toBe(3.0);
    expect((float) $response->json('data.items.0.unit_price'))->toBe(100.0);
    expect((float) $response->json('data.total_amount'))->toBe((float) $quotation['total_amount']);

    $this->assertDatabaseHas('quotations', ['id' => $quotation['id'], 'status' => 'converted']);
});

it('rejects converting an already-converted quotation instead of creating a second invoice', function () {
    $quotation = ($this->createDraftQuotation)();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice")->assertCreated();

    $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(\App\Models\Invoice::where('quotation_id', $quotation['id'])->count())->toBe(1);
});

it('never moves stock or posts a journal entry at any quotation stage', function () {
    $quotation = ($this->createDraftQuotation)();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice")->assertCreated();

    expect(StockMovement::where('product_id', $this->product->id)->count())->toBe(0);
    expect(JournalEntry::count())->toBe(0);
});
