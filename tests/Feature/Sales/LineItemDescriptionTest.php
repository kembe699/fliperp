<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('stores and returns a short description on a quotation line item', function () {
    $response = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'description' => 'Installed on-site, includes 1yr warranty'],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.items.0.description'))->toBe('Installed on-site, includes 1yr warranty');

    $quotationId = $response->json('data.id');
    $this->getJson("/api/v1/quotations/{$quotationId}")
        ->assertOk()
        ->assertJsonPath('data.items.0.description', 'Installed on-site, includes 1yr warranty');
});

it('carries the line item description over when a quotation converts to an invoice', function () {
    $quotation = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'description' => 'Custom bracket, blue finish'],
        ],
    ])->json('data');

    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk();

    $invoice = $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice")->json('data');

    expect($invoice['items'][0]['description'])->toBe('Custom bracket, blue finish');
});

it('allows a description on an invoice line item created directly', function () {
    $response = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'due_date' => now()->addDays(14)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'description' => 'Rush order'],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.items.0.description'))->toBe('Rush order');
});

it('rejects a description longer than 1000 characters', function () {
    $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'description' => str_repeat('x', 1001)],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.description');
});
