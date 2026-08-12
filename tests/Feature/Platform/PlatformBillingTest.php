<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->platform, $this->platformBranch, $this->platformStaff] = createPlatformCompany();
    Sanctum::actingAs($this->platformStaff, ['*']);

    Mail::fake();
    $this->client = Company::find($this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Billed Co', 'admin_name' => 'A', 'admin_email' => 'a@billedco.test',
    ])->json('data.company.id'));

    // Products live under whichever company creates them — the platform company
    // needs its own product catalog to bill from, same as any normal tenant would.
    $this->product = createProduct($this->platform, ['selling_price' => 250]);
});

it('creates a quotation for a client via the platform-admin endpoint, identical in structure to a normal quotation', function () {
    $response = $this->postJson("/api/v1/platform-admin/clients/{$this->client->id}/quotations", [
        'valid_until' => now()->addDays(14)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.customer_id'))->toBe($this->client->billing_customer_id);
    expect((float) $response->json('data.total_amount'))->toBe(500.0);
    expect($response->json('data.status'))->toBe('draft');

    $quotation = Quotation::find($response->json('data.id'));
    // Same TenantModel scoping as any other quotation — belongs to the PLATFORM
    // company (the acting user's own home tenant), not the client.
    expect($quotation->company_id)->toBe($this->platform->id);
    expect($quotation->items)->toHaveCount(1);

    // Shows up in the platform company's own ordinary quotations list, unmodified.
    $listed = collect($this->getJson('/api/v1/quotations')->json('data'))->pluck('id');
    expect($listed)->toContain($quotation->id);
});

it('creates an invoice for a client via the platform-admin endpoint, identical in structure to a normal invoice', function () {
    $response = $this->postJson("/api/v1/platform-admin/clients/{$this->client->id}/invoices", [
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 250, 'description' => 'Monthly subscription'],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.customer_id'))->toBe($this->client->billing_customer_id);
    expect((float) $response->json('data.total_amount'))->toBe(250.0);
    expect($response->json('data.items.0.description'))->toBe('Monthly subscription');

    $invoice = Invoice::find($response->json('data.id'));
    expect($invoice->company_id)->toBe($this->platform->id);

    $listed = collect($this->getJson('/api/v1/invoices')->json('data'))->pluck('id');
    expect($listed)->toContain($invoice->id);
});

it('rejects billing a client with no billing_customer_id set up', function () {
    // Not realistically reachable via the normal onboarding flow (it always sets
    // one), but guards a client row that was hand-edited or migrated oddly.
    $this->client->update(['billing_customer_id' => null]);

    $this->postJson("/api/v1/platform-admin/clients/{$this->client->id}/invoices", [
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 250]],
    ])->assertStatus(422);
});

it('reflects invoiced/collected/outstanding correctly in the platform-wide billing summary', function () {
    $this->postJson("/api/v1/platform-admin/clients/{$this->client->id}/invoices", [
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250]],
    ])->assertCreated();

    $response = $this->getJson('/api/v1/platform-admin/billing/summary');

    $response->assertOk();
    expect((float) $response->json('data.total_invoiced'))->toBe(500.0);
    expect((float) $response->json('data.total_outstanding'))->toBe(500.0);

    $byClient = collect($response->json('data.by_client'))->firstWhere('company_id', $this->client->id);
    expect((float) $byClient['total_invoiced'])->toBe(500.0);
});

it('blocks a normal (non-platform-staff) user from platform-admin billing endpoints', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $regularAdmin = createUserWithRole('company_admin', $company, $branch);
    Sanctum::actingAs($regularAdmin, ['*']);

    $this->getJson('/api/v1/platform-admin/billing/summary')->assertStatus(403);
});
