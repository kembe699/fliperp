<?php

use App\Models\CrmMeetingAttendee;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->staff = createUserWithRole('branch_manager', $this->company, $this->branch);
    $this->customer = createCustomer($this->company, ['name' => 'Acme Ltd', 'email' => 'acme@example.test']);

    $this->stage = createCrmPipelineStage($this->company, ['name' => 'New Lead', 'position' => 1]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->lead = $this->postJson('/api/v1/crm/leads', [
        'name' => 'Jane Lead',
        'email' => 'jane@example.test',
    ])->json('data');
});

it('rejects a meeting with no lead, deal or customer', function () {
    $this->postJson('/api/v1/crm/meetings', [
        'title' => 'Kickoff call',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ])->assertStatus(422);
});

it('creates a meeting linked to a lead with internal and external attendee rows', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Discovery call',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'duration_minutes' => 45,
        'organizer_id' => $this->staff->id,
        'attendees' => [
            ['user_id' => $this->staff->id],
            ['external_name' => 'Jane Lead', 'external_email' => 'jane@example.test'],
        ],
    ]);

    $response->assertCreated();
    $meetingId = $response->json('data.id');

    $attendees = CrmMeetingAttendee::where('meeting_id', $meetingId)->get();
    expect($attendees)->toHaveCount(2);
    expect($attendees->pluck('user_id')->filter()->all())->toContain($this->staff->id);
    expect($attendees->pluck('external_email')->filter()->all())->toContain('jane@example.test');

    Notification::assertSentTo($this->staff, \App\Notifications\CrmMeetingScheduled::class);
});

it('defaults attendees to the organizer plus the lead contact when none are given', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Discovery call',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $this->staff->id,
    ]);

    $response->assertCreated();
    $attendees = CrmMeetingAttendee::where('meeting_id', $response->json('data.id'))->get();

    expect($attendees->pluck('user_id')->filter()->all())->toContain($this->staff->id);
    expect($attendees->pluck('external_email')->filter()->all())->toContain('jane@example.test');
});

it('downloads a valid, parseable .ics file for a meeting', function () {
    $response = $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Discovery call',
        'description' => 'Intro, needs assessment',
        'scheduled_at' => '2026-09-01 14:00:00',
        'duration_minutes' => 30,
        'organizer_id' => $this->admin->id,
    ]);
    $meetingId = $response->json('data.id');

    $ics = $this->get("/api/v1/crm/meetings/{$meetingId}/ics");

    $ics->assertOk();
    $ics->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $body = $ics->getContent();
    expect($body)->toContain('BEGIN:VCALENDAR');
    expect($body)->toContain('BEGIN:VEVENT');
    expect($body)->toContain('SUMMARY:Discovery call');
    expect($body)->toContain('DTSTART:20260901T140000Z');
    expect($body)->toContain('DTEND:20260901T143000Z');
    expect($body)->toContain('END:VEVENT');
    expect($body)->toContain('END:VCALENDAR');
    // RFC 5545 line endings are CRLF.
    expect($body)->toContain("\r\n");
});

it('transitions a meeting through status updates', function () {
    $response = $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Discovery call',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $this->admin->id,
    ]);
    $meetingId = $response->json('data.id');

    $this->patchJson("/api/v1/crm/meetings/{$meetingId}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->patchJson("/api/v1/crm/meetings/{$meetingId}/status", ['status' => 'not-a-status'])
        ->assertStatus(422);
});

it('filters meetings by organizer_id', function () {
    $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Owned by admin',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $this->admin->id,
    ])->assertCreated();

    $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Owned by staff',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $this->staff->id,
    ])->assertCreated();

    $response = $this->getJson('/api/v1/crm/meetings?organizer_id='.$this->staff->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Owned by staff');
});

it('counts a staff member upcoming scheduled meetings in the CRM staff report', function () {
    $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Future meeting',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $this->staff->id,
    ])->assertCreated();

    $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Already happened',
        'scheduled_at' => now()->subWeek()->toDateTimeString(),
        'organizer_id' => $this->staff->id,
    ])->assertCreated();

    $cancelledMeetingId = $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $this->lead['id'],
        'title' => 'Cancelled meeting',
        'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        'organizer_id' => $this->staff->id,
    ])->json('data.id');
    $this->patchJson("/api/v1/crm/meetings/{$cancelledMeetingId}/status", ['status' => 'cancelled'])->assertOk();

    $response = $this->getJson('/api/v1/crm/reports/staff');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('user_id', $this->staff->id);
    expect($row['upcoming_meetings_count'])->toBe(1);
});

it('returns 404 for a meeting belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);
    $otherLead = $this->postJson('/api/v1/crm/leads', ['name' => 'Other Lead'])->json('data');
    $foreignMeeting = $this->postJson('/api/v1/crm/meetings', [
        'lead_id' => $otherLead['id'],
        'title' => 'Foreign meeting',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'organizer_id' => $otherAdmin->id,
    ])->json('data');

    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson("/api/v1/crm/meetings/{$foreignMeeting['id']}")->assertStatus(404);
});
