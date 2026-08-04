<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->quotationId = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'reference_number' => 'QUO-PDF-TEST-001',
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100],
        ],
    ])->json('data.id');
});

it('returns a PDF for a quotation with the correct content type', function () {
    $response = $this->get("/api/v1/quotations/{$this->quotationId}/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('defaults to an inline disposition and switches to attachment with ?download=1', function () {
    $inline = $this->get("/api/v1/quotations/{$this->quotationId}/pdf");
    expect($inline->headers->get('Content-Disposition'))->toContain('inline');

    $attachment = $this->get("/api/v1/quotations/{$this->quotationId}/pdf?download=1");
    expect($attachment->headers->get('Content-Disposition'))->toContain('attachment');
});

it('returns 404 for a quotation belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherCustomer = createCustomer($otherCompany);
    $otherProduct = createProduct($otherCompany, ['cost_price' => 50, 'selling_price' => 100]);
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);

    Sanctum::actingAs($otherAdmin, ['*']);

    $otherQuotationId = $this->postJson('/api/v1/quotations', [
        'branch_id' => $otherBranch->id,
        'customer_id' => $otherCustomer->id,
        'reference_number' => 'QUO-PDF-TEST-OTHER',
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [['product_id' => $otherProduct->id, 'quantity' => 1, 'unit_price' => 50]],
    ])->json('data.id');

    Sanctum::actingAs($this->admin, ['*']);

    $this->get("/api/v1/quotations/{$otherQuotationId}/pdf")->assertStatus(404);
    $this->get("/api/v1/quotations/{$this->quotationId}/pdf")->assertOk();
});
