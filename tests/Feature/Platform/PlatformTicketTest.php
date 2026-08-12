<?php

use App\Models\User;
use App\Notifications\NewSupportTicket;
use App\Notifications\SupportTicketReplied;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->platform, $this->platformBranch, $this->platformStaff] = createPlatformCompany();
    [$this->companyA, $this->branchA] = createCompanyWithMainBranch();
    [$this->companyB, $this->branchB] = createCompanyWithMainBranch();
    $this->userA = createUserWithRole('company_admin', $this->companyA, $this->branchA);
    $this->userB = createUserWithRole('company_admin', $this->companyB, $this->branchB);
});

it('lets a client raise a ticket for their own company and notifies platform staff', function () {
    Notification::fake();
    Sanctum::actingAs($this->userA, ['*']);

    $response = $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Cannot print invoices',
        'description' => 'The PDF button does nothing.',
        'priority' => 'high',
    ]);

    $response->assertCreated();
    expect($response->json('data.company_id'))->toBe($this->companyA->id);
    expect($response->json('data.raised_by_user_id'))->toBe($this->userA->id);
    expect($response->json('data.source'))->toBe('contact_support_widget');

    Notification::assertSentTo($this->platformStaff, NewSupportTicket::class);
});

it('lets a client see and reply to their own ticket, but not another company\'s ticket', function () {
    Sanctum::actingAs($this->userA, ['*']);
    $ticketId = $this->postJson('/api/v1/support/tickets', [
        'subject' => 'A ticket', 'description' => 'Details.',
    ])->json('data.id');

    $this->getJson("/api/v1/support/tickets/{$ticketId}")->assertOk();
    $this->postJson("/api/v1/support/tickets/{$ticketId}/replies", ['body' => 'Any update?'])
        ->assertCreated()
        ->assertJsonPath('data.replies.0.body', 'Any update?')
        ->assertJsonPath('data.replies.0.is_internal_note', false);

    // Company B's user can't even resolve the ticket id — CompanyScope 404s it,
    // same as any other tenant-owned resource.
    Sanctum::actingAs($this->userB, ['*']);
    $this->getJson("/api/v1/support/tickets/{$ticketId}")->assertStatus(404);
});

it('only lists a client\'s own company\'s tickets, never another company\'s', function () {
    Sanctum::actingAs($this->userA, ['*']);
    $this->postJson('/api/v1/support/tickets', ['subject' => 'A ticket', 'description' => 'x'])->assertCreated();

    Sanctum::actingAs($this->userB, ['*']);
    $this->postJson('/api/v1/support/tickets', ['subject' => 'B ticket', 'description' => 'x'])->assertCreated();

    $response = $this->getJson('/api/v1/support/tickets');
    $subjects = collect($response->json('data'))->pluck('subject');
    expect($subjects)->toContain('B ticket');
    expect($subjects)->not->toContain('A ticket');
});

it('lets platform staff see tickets from every company, filterable by status/priority/assigned_to/company', function () {
    Sanctum::actingAs($this->userA, ['*']);
    $ticketA = $this->postJson('/api/v1/support/tickets', ['subject' => 'A ticket', 'description' => 'x', 'priority' => 'urgent'])->json('data.id');

    Sanctum::actingAs($this->userB, ['*']);
    $this->postJson('/api/v1/support/tickets', ['subject' => 'B ticket', 'description' => 'x'])->assertCreated();

    Sanctum::actingAs($this->platformStaff, ['*']);
    $all = collect($this->getJson('/api/v1/platform-admin/tickets')->json('data'))->pluck('subject');
    expect($all)->toContain('A ticket', 'B ticket');

    $byCompany = $this->getJson("/api/v1/platform-admin/tickets?company_id={$this->companyA->id}");
    expect(collect($byCompany->json('data'))->pluck('subject')->all())->toBe(['A ticket']);

    $byPriority = $this->getJson('/api/v1/platform-admin/tickets?priority=urgent');
    expect(collect($byPriority->json('data'))->pluck('id'))->toContain($ticketA);
});

it('lets platform staff assign, reprioritize and change the status of any ticket', function () {
    Sanctum::actingAs($this->userA, ['*']);
    $ticketId = $this->postJson('/api/v1/support/tickets', ['subject' => 'x', 'description' => 'x'])->json('data.id');

    Sanctum::actingAs($this->platformStaff, ['*']);
    $response = $this->patchJson("/api/v1/platform-admin/tickets/{$ticketId}", [
        'assigned_to' => $this->platformStaff->id,
        'priority' => 'urgent',
        'status' => 'in_progress',
    ]);

    $response->assertOk();
    expect($response->json('data.assigned_to'))->toBe($this->platformStaff->id);
    expect($response->json('data.priority'))->toBe('urgent');
    expect($response->json('data.status'))->toBe('in_progress');
});

it('lets platform staff post a client-visible reply, notifying the user who raised it, or an internal note that stays hidden from the client', function () {
    Notification::fake();
    Sanctum::actingAs($this->userA, ['*']);
    $ticketId = $this->postJson('/api/v1/support/tickets', ['subject' => 'x', 'description' => 'x'])->json('data.id');

    Sanctum::actingAs($this->platformStaff, ['*']);
    $this->postJson("/api/v1/platform-admin/tickets/{$ticketId}/replies", [
        'body' => 'We are looking into it.',
    ])->assertCreated();

    $this->postJson("/api/v1/platform-admin/tickets/{$ticketId}/replies", [
        'body' => 'Internal: this is a known bug, ETA Friday.',
        'is_internal_note' => true,
    ])->assertCreated();

    // Staff-side view sees both replies.
    $staffView = $this->getJson("/api/v1/platform-admin/tickets/{$ticketId}");
    expect($staffView->json('data.replies'))->toHaveCount(2);

    // Client-side view NEVER sees the internal note.
    Sanctum::actingAs($this->userA, ['*']);
    $clientView = $this->getJson("/api/v1/support/tickets/{$ticketId}");
    $bodies = collect($clientView->json('data.replies'))->pluck('body');
    expect($bodies)->toContain('We are looking into it.');
    expect($bodies)->not->toContain('Internal: this is a known bug, ETA Friday.');
    expect(collect($clientView->json('data.replies'))->pluck('is_internal_note'))->each->toBeFalse();

    Notification::assertSentTo($this->userA, SupportTicketReplied::class);
});

it('never notifies the client of an internal note', function () {
    Notification::fake();
    Sanctum::actingAs($this->userA, ['*']);
    $ticketId = $this->postJson('/api/v1/support/tickets', ['subject' => 'x', 'description' => 'x'])->json('data.id');

    Sanctum::actingAs($this->platformStaff, ['*']);
    $this->postJson("/api/v1/platform-admin/tickets/{$ticketId}/replies", [
        'body' => 'Internal only.',
        'is_internal_note' => true,
    ])->assertCreated();

    Notification::assertNotSentTo($this->userA, SupportTicketReplied::class);
});

it('blocks a normal user from every /platform-admin/tickets endpoint', function () {
    Sanctum::actingAs($this->userA, ['*']);
    $this->getJson('/api/v1/platform-admin/tickets')->assertStatus(403);
});
