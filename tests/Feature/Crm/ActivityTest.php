<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->stage = createCrmPipelineStage($this->company);

    Sanctum::actingAs($this->admin, ['*']);
});

it('rejects an activity with no customer, lead or deal', function () {
    $this->postJson('/api/v1/crm/activities', [
        'type' => 'note',
        'subject' => 'Orphan activity',
        'activity_date' => now()->toDateString(),
    ])->assertStatus(422);
});

it('links an activity to a customer', function () {
    $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->customer->id,
        'type' => 'call',
        'subject' => 'Check-in call',
        'activity_date' => now()->toDateString(),
    ])->assertCreated()->assertJsonPath('data.customer_id', $this->customer->id);
});

it('links an activity to a lead', function () {
    $lead = App\Models\CrmLead::create([
        'company_id' => $this->company->id,
        'name' => 'Some Lead',
        'status' => 'open',
    ]);

    $this->postJson('/api/v1/crm/activities', [
        'lead_id' => $lead->id,
        'type' => 'email',
        'subject' => 'Sent proposal',
        'activity_date' => now()->toDateString(),
    ])->assertCreated()->assertJsonPath('data.lead_id', $lead->id);
});

it('links an activity to a deal', function () {
    $deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Deal for activity',
        'value' => 100,
    ])->json('data');

    $this->postJson('/api/v1/crm/activities', [
        'deal_id' => $deal['id'],
        'type' => 'meeting',
        'subject' => 'Discovery call',
        'activity_date' => now()->toDateString(),
    ])->assertCreated()->assertJsonPath('data.deal_id', $deal['id']);
});

it('logs a complaint as open and filters activities by type and status', function () {
    $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->customer->id,
        'type' => 'complaint',
        'subject' => 'Late delivery',
        'activity_date' => now()->toDateString(),
    ])->assertCreated()->assertJsonPath('data.status', 'open');

    $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->customer->id,
        'type' => 'note',
        'subject' => 'Unrelated note',
        'activity_date' => now()->toDateString(),
    ])->assertCreated();

    $complaints = $this->getJson('/api/v1/crm/activities?type=complaint&status=open')->json('data');
    expect($complaints)->toHaveCount(1);
    expect($complaints[0]['subject'])->toBe('Late delivery');
});

it('resolves an open complaint and records who resolved it', function () {
    $complaint = $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->customer->id,
        'type' => 'complaint',
        'subject' => 'Product defect',
        'activity_date' => now()->toDateString(),
    ])->json('data');

    $response = $this->postJson("/api/v1/crm/activities/{$complaint['id']}/resolve")->assertOk();

    expect($response->json('data.status'))->toBe('resolved');
    expect($response->json('data.resolved_at'))->not->toBeNull();
    expect($response->json('data.resolved_by.id'))->toBe($this->admin->id);

    $this->postJson("/api/v1/crm/activities/{$complaint['id']}/resolve")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
