<?php

use App\Models\CrmLead;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->stage = createCrmPipelineStage($this->company, ['name' => 'New Lead', 'position' => 1]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createLead = function (array $overrides = []) {
        return $this->postJson('/api/v1/crm/leads', array_merge([
            'branch_id' => $this->branch->id,
            'name' => 'Alice Wanjiru',
            'company_name' => 'Wanjiru Enterprises',
            'email' => 'alice@example.test',
            'phone' => '+254711000111',
            'source' => 'referral',
        ], $overrides))->json('data');
    };
});

it('creates a lead as open by default', function () {
    $lead = ($this->createLead)();

    expect($lead['status'])->toBe('open');
});

it('converts an open lead into a real customer, mapping name, email and phone', function () {
    $lead = ($this->createLead)();

    $response = $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")
        ->assertOk()
        ->assertJsonPath('data.status', 'converted');

    $customerId = $response->json('data.converted_customer_id');
    expect($customerId)->not->toBeNull();

    $customer = App\Models\Customer::find($customerId);
    expect($customer->name)->toBe('Wanjiru Enterprises')
        ->and($customer->email)->toBe('alice@example.test')
        ->and($customer->phone)->toBe('+254711000111')
        ->and($customer->customer_type)->toBe('regular');
});

it('re-points an open deal to the newly created customer when its lead converts', function () {
    $lead = ($this->createLead)();

    $deal = $this->postJson('/api/v1/crm/deals', [
        'lead_id' => $lead['id'],
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Wanjiru Enterprises — website',
        'value' => 1000,
    ])->json('data');

    expect($deal['customer_id'])->toBeNull();

    $response = $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")->assertOk();
    $customerId = $response->json('data.converted_customer_id');

    $freshDeal = $this->getJson("/api/v1/crm/deals/{$deal['id']}")->json('data');
    expect($freshDeal['customer_id'])->toBe($customerId);
});

it('does not re-point a deal that was already closed before conversion', function () {
    $wonStage = createCrmPipelineStage($this->company, ['name' => 'Closed Won', 'position' => 2, 'is_closed_won' => true]);
    $lead = ($this->createLead)();

    $deal = $this->postJson('/api/v1/crm/deals', [
        'lead_id' => $lead['id'],
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Already closing',
        'value' => 500,
    ])->json('data');

    $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", ['pipeline_stage_id' => $wonStage->id])->assertOk();

    $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")->assertOk();

    $freshDeal = $this->getJson("/api/v1/crm/deals/{$deal['id']}")->json('data');
    expect($freshDeal['customer_id'])->toBeNull();
});

it('requires a phone number to convert a lead that never captured one', function () {
    $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'No Phone Lead'])->json('data');

    $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $response = $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert", ['phone' => '+211900111222'])
        ->assertOk();

    $customer = App\Models\Customer::find($response->json('data.converted_customer_id'));
    expect($customer->phone)->toBe('+211900111222');
});

it('rejects converting a lead that is already converted', function () {
    $lead = ($this->createLead)();
    $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")->assertOk();

    $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects converting a disqualified lead', function () {
    $lead = ($this->createLead)();
    $this->putJson("/api/v1/crm/leads/{$lead['id']}", ['status' => 'disqualified'])->assertOk();

    $this->postJson("/api/v1/crm/leads/{$lead['id']}/convert")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('blocks editing a lead that is no longer open', function () {
    $lead = ($this->createLead)();
    $this->putJson("/api/v1/crm/leads/{$lead['id']}", ['status' => 'disqualified'])->assertOk();

    $this->putJson("/api/v1/crm/leads/{$lead['id']}", ['name' => 'Should not save'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 for a lead belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    $otherLead = CrmLead::create([
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'name' => 'Other Co Lead',
        'status' => 'open',
    ]);

    $this->getJson("/api/v1/crm/leads/{$otherLead->id}")->assertStatus(404);

    Sanctum::actingAs($otherAdmin, ['*']);
    $this->getJson("/api/v1/crm/leads/{$otherLead->id}")->assertOk();
});
