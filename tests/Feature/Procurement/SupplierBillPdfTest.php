<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->supplier = createSupplier($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    $this->billId = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'BILL-PDF-TEST-001',
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 500,
        'tax_amount' => 50,
    ])->json('data.id');
});

it('returns a PDF for a supplier bill with the correct content type', function () {
    $response = $this->get("/api/v1/supplier-bills/{$this->billId}/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('returns 404 for a supplier bill belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);

    $this->get("/api/v1/supplier-bills/{$this->billId}/pdf")->assertStatus(404);
});
