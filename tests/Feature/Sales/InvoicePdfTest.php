<?php

use App\Mail\DocumentMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company, ['email' => 'customer@example.test']);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->invoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100],
        ],
    ])->json('data.id');
});

it('returns a PDF for an invoice with the correct content type', function () {
    $response = $this->get("/api/v1/invoices/{$this->invoiceId}/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('defaults to an inline disposition and switches to attachment with ?download=1', function () {
    $inline = $this->get("/api/v1/invoices/{$this->invoiceId}/pdf");
    expect($inline->headers->get('Content-Disposition'))->toContain('inline');

    $attachment = $this->get("/api/v1/invoices/{$this->invoiceId}/pdf?download=1");
    expect($attachment->headers->get('Content-Disposition'))->toContain('attachment');
});

it('returns 404 for an invoice belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherCustomer = createCustomer($otherCompany);
    $otherProduct = createProduct($otherCompany, ['cost_price' => 50, 'selling_price' => 100]);
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);

    Sanctum::actingAs($otherAdmin, ['*']);
    seedChartOfAccounts($otherCompany);

    $otherInvoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $otherBranch->id,
        'customer_id' => $otherCustomer->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [['product_id' => $otherProduct->id, 'quantity' => 1, 'unit_price' => 50]],
    ])->json('data.id');

    // Switch back to the original company's admin and try to reach the other company's invoice PDF.
    Sanctum::actingAs($this->admin, ['*']);

    $this->get("/api/v1/invoices/{$otherInvoiceId}/pdf")->assertStatus(404);
    $this->get("/api/v1/invoices/{$this->invoiceId}/pdf")->assertOk();
});

it('emails the invoice PDF to the customer on file', function () {
    Mail::fake();

    $this->postJson("/api/v1/invoices/{$this->invoiceId}/email")
        ->assertOk()
        ->assertJsonPath('message', 'Invoice emailed to customer@example.test.');

    Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) {
        return $mail->hasTo('customer@example.test') && $mail->documentType === 'Invoice';
    });
});

it('rejects emailing an invoice when the customer has no email on file', function () {
    $customerNoEmail = createCustomer($this->company, ['name' => 'No Email Customer']);
    $invoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => $customerNoEmail->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->json('data.id');

    Mail::fake();

    $this->postJson("/api/v1/invoices/{$invoiceId}/email")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    Mail::assertNothingSent();
});
