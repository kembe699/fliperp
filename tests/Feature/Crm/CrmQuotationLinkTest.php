<?php

use App\Mail\CrmDirectEmail;
use App\Models\CrmEmail;
use App\Models\Quotation;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company, [
        'name' => 'Acme Ltd',
        'email' => 'acme@example.test',
        'branch_id' => $this->branch->id,
    ]);
    $this->stage = createCrmPipelineStage($this->company, ['name' => 'New Lead', 'position' => 1]);
    $this->serviceA = createCrmService($this->company, ['name' => 'Consulting', 'default_price' => 250]);
    $this->serviceB = createCrmService($this->company, ['name' => 'Installation', 'default_price' => 400]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('creates a quotation from a deal using its attached services with correct line items and totals', function () {
    $deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Acme renewal',
        'value' => 650,
    ])->json('data');

    $this->postJson("/api/v1/crm/deals/{$deal['id']}/services", [
        'service_ids' => [$this->serviceA->id, $this->serviceB->id],
    ])->assertOk();

    $response = $this->postJson("/api/v1/crm/deals/{$deal['id']}/quotations", [
        'valid_until' => now()->addDays(14)->toDateString(),
    ]);

    $response->assertCreated();
    expect($response->json('data.customer_id'))->toBe($this->customer->id);

    $quotation = Quotation::with('items.product')->findOrFail($response->json('data.id'));
    expect($quotation->items)->toHaveCount(2);
    expect((float) $quotation->subtotal)->toBe(650.0);
    expect((float) $quotation->total_amount)->toBe(650.0);

    $productNames = $quotation->items->pluck('product.name')->sort()->values()->all();
    expect($productNames)->toBe(['Consulting', 'Installation']);

    // Each attached CrmService got a lazily-created, non-stock-tracked shadow product.
    expect($this->serviceA->fresh()->product)->not->toBeNull();
    expect($this->serviceA->fresh()->product->track_inventory)->toBeFalse();

    expect($deal['id'])->not->toBeNull();
});

it('falls back to the company main branch when the customer has no branch_id, instead of a 500', function () {
    $branchlessCustomer = createCustomer($this->company, ['name' => 'No Branch Ltd', 'branch_id' => null]);
    $deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $branchlessCustomer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Branchless deal',
        'value' => 250,
    ])->json('data');
    $this->postJson("/api/v1/crm/deals/{$deal['id']}/services", ['service_ids' => [$this->serviceA->id]])->assertOk();

    $response = $this->postJson("/api/v1/crm/deals/{$deal['id']}/quotations", []);

    $response->assertCreated();
    expect($response->json('data.branch_id'))->toBe($this->branch->id);
});

it('requires the deal to have a linked customer before quoting', function () {
    $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Orphan Lead'])->json('data');
    $deal = $this->postJson('/api/v1/crm/deals', [
        'lead_id' => $lead['id'],
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Not yet a customer',
        'value' => 100,
    ])->json('data');

    $this->postJson("/api/v1/crm/deals/{$deal['id']}/quotations", [])->assertStatus(422);
});

it('rejects creating a quotation from a lead that has not been converted', function () {
    $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Jane Lead', 'phone' => '+15550001'])->json('data');
    $this->postJson("/api/v1/crm/leads/{$lead['id']}/services", ['service_ids' => [$this->serviceA->id]])->assertOk();

    $this->postJson("/api/v1/crm/leads/{$lead['id']}/quotations", [])->assertStatus(422);
});

it('creates a quotation from a converted lead using its attached services', function () {
    $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Jane Lead', 'phone' => '+15550001'])->json('data');
    $this->postJson("/api/v1/crm/leads/{$lead['id']}/services", ['service_ids' => [$this->serviceA->id]])->assertOk();

    $converted = $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert", ['branch_id' => $this->branch->id])->json('data');
    expect($converted['converted_customer_id'])->not->toBeNull();

    $response = $this->postJson("/api/v1/crm/leads/{$lead['id']}/quotations", []);

    $response->assertCreated();
    expect($response->json('data.customer_id'))->toBe($converted['converted_customer_id']);

    $quotation = Quotation::with('items')->findOrFail($response->json('data.id'));
    expect($quotation->items)->toHaveCount(1);
    expect((float) $quotation->total_amount)->toBe(250.0);
});

it('sends a quotation to the contact with a real PDF attachment and logs a crm email row', function () {
    Mail::fake();

    $deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Acme renewal',
        'value' => 250,
    ])->json('data');
    $this->postJson("/api/v1/crm/deals/{$deal['id']}/services", ['service_ids' => [$this->serviceA->id]])->assertOk();
    $quotation = $this->postJson("/api/v1/crm/deals/{$deal['id']}/quotations", [])->json('data');

    $response = $this->postJson("/api/v1/crm/quotations/{$quotation['id']}/send-to-contact", [
        'deal_id' => $deal['id'],
    ]);

    $response->assertCreated();
    expect($response->json('data.to_email'))->toBe('acme@example.test');
    expect($response->json('data.quotation_id'))->toBe($quotation['id']);

    $crmEmail = CrmEmail::findOrFail($response->json('data.id'));
    expect($crmEmail->status)->toBe('sent');
    expect($crmEmail->deal_id)->toBe($deal['id']);

    Mail::assertSent(CrmDirectEmail::class, function (CrmDirectEmail $mail) use ($quotation) {
        expect($mail->pdfContent)->not->toBeNull();
        expect(substr($mail->pdfContent, 0, 4))->toBe('%PDF');
        expect($mail->pdfFilename)->toContain($quotation['reference_number']);

        return true;
    });
});

it('converts a CRM-linked quotation to an invoice identically to the direct flow', function () {
    $deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Acme renewal',
        'value' => 250,
    ])->json('data');
    $this->postJson("/api/v1/crm/deals/{$deal['id']}/services", ['service_ids' => [$this->serviceA->id]])->assertOk();
    $quotation = $this->postJson("/api/v1/crm/deals/{$deal['id']}/quotations", [])->json('data');

    $this->postJson("/api/v1/quotations/{$quotation['id']}/send")->assertOk();
    $this->postJson("/api/v1/quotations/{$quotation['id']}/accept")->assertOk();

    $response = $this->postJson("/api/v1/quotations/{$quotation['id']}/convert-to-invoice");

    $response->assertCreated();
    expect((float) $response->json('data.total_amount'))->toBe(250.0);
    expect($response->json('data.items'))->toHaveCount(1);

    $freshQuotation = Quotation::findOrFail($quotation['id']);
    expect($freshQuotation->status)->toBe('converted');
});
