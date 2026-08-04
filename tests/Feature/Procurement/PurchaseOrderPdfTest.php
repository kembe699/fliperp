<?php

use App\Mail\DocumentMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->supplier = createSupplier($this->company, ['email' => 'supplier@example.test']);
    $this->product = createProduct($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    $this->poId = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id,
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'PO-PDF-TEST-001',
        'order_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity_ordered' => 10, 'unit_cost' => 100],
        ],
    ])->json('data.id');
});

it('returns a PDF for a purchase order with the correct content type', function () {
    $response = $this->get("/api/v1/purchase-orders/{$this->poId}/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('returns 404 for a purchase order belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);

    $this->get("/api/v1/purchase-orders/{$this->poId}/pdf")->assertStatus(404);
});

it('emails the purchase order PDF to the supplier on file', function () {
    Mail::fake();

    $this->postJson("/api/v1/purchase-orders/{$this->poId}/email")
        ->assertOk()
        ->assertJsonPath('message', 'Purchase order emailed to supplier@example.test.');

    Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) {
        return $mail->hasTo('supplier@example.test')
            && $mail->documentType === 'Purchase Order'
            && $mail->referenceNumber === 'PO-PDF-TEST-001'
            && $mail->pdfFilename === 'purchase-order-PO-PDF-TEST-001.pdf';
    });
});

it('rejects emailing a purchase order when the supplier has no email on file', function () {
    $supplierNoEmail = createSupplier($this->company, ['name' => 'No Email Supplier', 'email' => null]);
    $poId = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id,
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $supplierNoEmail->id,
        'reference_number' => 'PO-PDF-TEST-002',
        'order_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity_ordered' => 5, 'unit_cost' => 50]],
    ])->json('data.id');

    Mail::fake();

    $this->postJson("/api/v1/purchase-orders/{$poId}/email")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    Mail::assertNothingSent();
});
