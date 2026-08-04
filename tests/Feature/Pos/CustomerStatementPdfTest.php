<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);

    Sanctum::actingAs($this->cashier, ['*']);
});

it('returns a PDF for a customer statement with the correct content type', function () {
    $response = $this->get("/api/v1/customers/{$this->customer->id}/statement/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('defaults to an inline disposition and switches to attachment with ?download=1', function () {
    $inline = $this->get("/api/v1/customers/{$this->customer->id}/statement/pdf");
    expect($inline->headers->get('Content-Disposition'))->toContain('inline');

    $attachment = $this->get("/api/v1/customers/{$this->customer->id}/statement/pdf?download=1");
    expect($attachment->headers->get('Content-Disposition'))->toContain('attachment');
});

it('returns 404 for a customer belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherCustomer = createCustomer($otherCompany);
    createUserWithRole('cashier', $otherCompany, $otherBranch);

    $this->get("/api/v1/customers/{$otherCustomer->id}/statement/pdf")->assertStatus(404);
    $this->get("/api/v1/customers/{$this->customer->id}/statement/pdf")->assertOk();
});
