<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company, ['name' => 'Acme Ltd']);
    $this->stage = createCrmPipelineStage($this->company, ['name' => 'New Lead', 'position' => 1]);
    $this->service = createCrmService($this->company, ['name' => 'Consulting', 'default_price' => 250]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('returns a complete aggregated detail payload for a lead', function () {
    $lead = $this->postJson('/api/v1/crm/leads', [
        'name' => 'Jane Lead',
        'email' => 'jane@example.test',
    ])->json('data');

    $this->postJson("/api/v1/crm/leads/{$lead['id']}/services", ['service_ids' => [$this->service->id]])->assertOk();

    $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $lead['id'],
        'title' => 'Discovery call',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $this->admin->id,
    ])->assertCreated();

    $this->postJson('/api/v1/crm/activities', [
        'lead_id' => $lead['id'],
        'type' => 'note',
        'subject' => 'Left a voicemail',
        'activity_date' => now()->toDateString(),
    ])->assertCreated();

    $response = $this->getJson("/api/v1/crm/leads/{$lead['id']}/detail");

    $response->assertOk();
    $response->assertJsonPath('data.lead.id', $lead['id']);
    expect($response->json('data.services'))->toHaveCount(1);
    expect($response->json('data.services.0.name'))->toBe('Consulting');
    expect($response->json('data.meetings.upcoming'))->toHaveCount(1);
    expect($response->json('data.meetings.past'))->toHaveCount(0);
    expect($response->json('data.activities'))->toHaveCount(1);
    // Scheduling the meeting above auto-emails the lead's own contact
    // (jane@example.test, the default external attendee) — see MeetingTest
    // for dedicated coverage of that behavior.
    expect($response->json('data.emails'))->toHaveCount(1);
    expect($response->json('data.emails.0.meeting_id'))->not->toBeNull();
    expect($response->json('data.quotations'))->toHaveCount(0);
});

it('returns a complete aggregated detail payload for a deal', function () {
    $deal = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->stage->id,
        'title' => 'Acme renewal',
        'value' => 1000,
    ])->json('data');

    $this->postJson("/api/v1/crm/deals/{$deal['id']}/services", ['service_ids' => [$this->service->id]])->assertOk();

    $response = $this->getJson("/api/v1/crm/deals/{$deal['id']}/detail");

    $response->assertOk();
    $response->assertJsonPath('data.deal.id', $deal['id']);
    expect($response->json('data.services'))->toHaveCount(1);
    expect($response->json('data.meetings.upcoming'))->toHaveCount(0);
    expect($response->json('data.meetings.past'))->toHaveCount(0);
});

it('returns 404 for a lead detail belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);
    $otherLead = $this->postJson('/api/v1/crm/leads', ['name' => 'Other Lead'])->json('data');

    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson("/api/v1/crm/leads/{$otherLead['id']}/detail")->assertStatus(404);
});
