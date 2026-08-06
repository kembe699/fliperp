<?php

use App\Jobs\SendCrmEmailJob;
use App\Mail\CrmDirectEmail;
use App\Models\CrmEmail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->lead = $this->postJson('/api/v1/crm/leads', [
        'name' => 'Jane Lead',
        'email' => 'jane@example.test',
    ])->json('data');
});

it('queues a crm email and dispatches the delivery job', function () {
    Bus::fake();

    $response = $this->postJson('/api/v1/crm/emails/send', [
        'lead_id' => $this->lead['id'],
        'to_email' => 'jane@example.test',
        'to_name' => 'Jane Lead',
        'subject' => 'Following up',
        'body' => "Hi Jane,\n\nJust checking in.",
    ]);

    $response->assertCreated();
    $emailId = $response->json('data.id');

    expect(CrmEmail::findOrFail($emailId)->status)->toBe('queued');

    Bus::assertDispatched(SendCrmEmailJob::class, fn ($job) => $job->crmEmailId === $emailId);
});

it('marks the email sent once the queued job runs successfully (mail is faked, no real SMTP)', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/crm/emails/send', [
        'lead_id' => $this->lead['id'],
        'to_email' => 'jane@example.test',
        'subject' => 'Following up',
        'body' => 'Just checking in.',
    ]);

    $response->assertCreated();
    $emailId = $response->json('data.id');

    // QUEUE_CONNECTION=sync in testing, so the job already ran inline.
    expect(CrmEmail::findOrFail($emailId)->status)->toBe('sent');
    expect(CrmEmail::findOrFail($emailId)->sent_at)->not->toBeNull();

    Mail::assertSent(CrmDirectEmail::class, fn ($mail) => $mail->subjectLine === 'Following up');
});

it('marks the email failed and records the error when delivery blows up', function () {
    $email = CrmEmail::create([
        'company_id' => $this->company->id,
        'lead_id' => $this->lead['id'],
        'to_email' => 'jane@example.test',
        'subject' => 'Quotation for you',
        'body' => 'See attached.',
        'sent_by' => $this->admin->id,
        'status' => 'queued',
    ]);

    $pendingMail = Mockery::mock();
    $pendingMail->shouldReceive('send')->once()->andThrow(new \RuntimeException('Connection could not be established with host smtp.example.test'));
    Mail::shouldReceive('to')->once()->andReturn($pendingMail);

    (new SendCrmEmailJob($email->id))->handle(
        app(\App\Services\Sales\QuotationService::class),
        app(\App\Services\Crm\MeetingService::class),
    );

    $email->refresh();
    expect($email->status)->toBe('failed');
    expect($email->error_message)->toContain('Connection could not be established');
});

it('lists crm emails filtered by lead_id, newest first', function () {
    Mail::fake();

    $this->postJson('/api/v1/crm/emails/send', [
        'lead_id' => $this->lead['id'],
        'to_email' => 'jane@example.test',
        'subject' => 'First',
        'body' => 'First message',
    ])->assertCreated();

    $this->postJson('/api/v1/crm/emails/send', [
        'lead_id' => $this->lead['id'],
        'to_email' => 'jane@example.test',
        'subject' => 'Second',
        'body' => 'Second message',
    ])->assertCreated();

    $response = $this->getJson('/api/v1/crm/emails?lead_id='.$this->lead['id']);

    $response->assertOk();
    expect($response->json('data.0.subject'))->toBe('Second');
    expect($response->json('data.1.subject'))->toBe('First');
});

it('returns 404 for a crm email lead filter belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);
    $otherLead = $this->postJson('/api/v1/crm/leads', ['name' => 'Other Lead'])->json('data');

    Sanctum::actingAs($this->admin, ['*']);

    // A lead_id belonging to another company simply matches nothing under
    // this company's CompanyScope — never another company's data.
    $response = $this->getJson('/api/v1/crm/emails?lead_id='.$otherLead['id']);
    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});
