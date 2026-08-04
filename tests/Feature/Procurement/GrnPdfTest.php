<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->supplier = createSupplier($this->company);
    $this->product = createProduct($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    $this->grnId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-PDF-TEST-001',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity_received' => 5, 'unit_cost' => 200, 'condition' => 'good'],
        ],
    ])->json('data.id');
});

it('returns a PDF for a goods received note with the correct content type', function () {
    $response = $this->get("/api/v1/goods-received-notes/{$this->grnId}/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('returns 404 for a GRN belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);

    $this->get("/api/v1/goods-received-notes/{$this->grnId}/pdf")->assertStatus(404);
});
