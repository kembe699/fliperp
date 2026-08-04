<?php

use App\Models\JournalEntryLine;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->taxRate = createTaxRate($this->company, ['rate' => 10]);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createDraftInvoice = function (array $overrides = []) {
        return $this->postJson('/api/v1/invoices', array_merge([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 100, 'tax_rate_id' => $this->taxRate->id],
            ],
        ], $overrides))->json('data');
    };
});

it('posts a balanced journal entry debiting receivable and crediting revenue and tax on send', function () {
    $invoice = ($this->createDraftInvoice)();
    // subtotal = 400, tax = 40, total = 440

    $response = $this->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk();

    expect($response->json('data.status'))->toBe('sent');
    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();
    $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'posted']);

    $lines = JournalEntryLine::where('journal_entry_id', $journalEntryId)->get();
    expect((float) $lines->sum('debit'))->toBe((float) $lines->sum('credit'));

    $receivableLine = $lines->firstWhere('account_id', $this->accounts['1100']->id);
    $revenueLine = $lines->firstWhere('account_id', $this->accounts['4000']->id);
    $taxLine = $lines->firstWhere('account_id', $this->accounts['2300']->id);

    expect((float) $receivableLine->debit)->toBe(440.0);
    expect((float) $revenueLine->credit)->toBe(400.0);
    expect((float) $taxLine->credit)->toBe(40.0);
});

it('blocks editing and deleting an invoice once it is no longer draft', function () {
    $invoice = ($this->createDraftInvoice)();
    $this->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk();

    $this->putJson("/api/v1/invoices/{$invoice['id']}", ['discount_amount' => 10])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->deleteJson("/api/v1/invoices/{$invoice['id']}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('reverses the journal entry when a sent invoice is cancelled', function () {
    $invoice = ($this->createDraftInvoice)();
    $sent = $this->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk()->json('data');

    $this->postJson("/api/v1/invoices/{$invoice['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('journal_entries', ['id' => $sent['journal_entry_id'], 'status' => 'reversed']);
    $this->assertDatabaseHas('journal_entries', ['reference_number' => 'INV-JE-'.$invoice['reference_number'].'-REV', 'status' => 'posted']);
});

it('flags a sent invoice past its due date as overdue via the scope and refreshStatus', function () {
    $invoice = ($this->createDraftInvoice)(['due_date' => now()->subDays(5)->toDateString()]);
    $this->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk();

    // GET /invoices/overdue uses the query scope directly.
    $overdueIds = collect($this->getJson('/api/v1/invoices/overdue')->assertOk()->json('data'))->pluck('id')->all();
    expect($overdueIds)->toContain($invoice['id']);

    // GET /invoices/{id} refreshes and persists the status.
    $this->getJson("/api/v1/invoices/{$invoice['id']}")->assertOk()->assertJsonPath('data.status', 'overdue');
    $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'overdue']);
});

it('does not flag a draft invoice past its due date as overdue', function () {
    $invoice = ($this->createDraftInvoice)(['due_date' => now()->subDays(5)->toDateString()]);

    $overdueIds = collect($this->getJson('/api/v1/invoices/overdue')->assertOk()->json('data'))->pluck('id')->all();
    expect($overdueIds)->not->toContain($invoice['id']);
});
