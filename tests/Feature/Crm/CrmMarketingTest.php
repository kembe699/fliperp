<?php

use App\Mail\CrmCampaignEmail;
use App\Mail\CrmDirectEmail;
use App\Mail\CrmMeetingConfirmation;
use App\Models\CrmEmail;
use App\Models\CrmEmailCampaignRecipient;
use App\Models\CrmLead;
use App\Models\CrmMeeting;
use App\Services\Crm\EmailCampaignService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// CRM marketing: direct emails, public booking pages, lead magnets, email
// campaigns (batches, tracking, unsubscribe) and the analytics summary.
beforeEach(function () {
    Mail::fake();
    [$this->company, $this->branch] = createCompanyWithMainBranch(['status' => 'active', 'is_active' => true, 'enabled_modules' => null]);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
});

afterEach(fn () => Carbon::setTestNow());

/** Public requests carry no login. */
function asGuest(): void
{
    Auth::forgetGuards();
    app('auth')->guard('sanctum')->forgetUser();
}

function makeBookingPage(array $overrides = []): array
{
    return test()->postJson('/api/v1/crm/booking-pages', [
        'title' => 'Discovery call',
        'duration_minutes' => 30,
        'days' => [1, 2, 3, 4, 5, 6, 7],
        'start_time' => '09:00',
        'end_time' => '12:00',
        'timezone' => 'UTC',
        'min_notice_hours' => 0,
        'host_user_id' => test()->admin->id,
        ...$overrides,
    ])->assertCreated()->json('data');
}

it('sends a direct email under the company name with replies going to the sender', function () {
    $this->postJson('/api/v1/crm/emails/send', [
        'to_email' => 'client@example.test',
        'subject' => 'Hello',
        'body' => "Hi,\nSee https://example.test/offer.",
    ])->assertCreated();

    Mail::assertSent(CrmDirectEmail::class, function (CrmDirectEmail $mail) {
        $envelope = $mail->envelope();

        return $envelope->from->name === $this->company->name
            && $envelope->replyTo[0]->address === $this->admin->email
            && str_contains($mail->render(), 'href="https://example.test/offer"');
    });
    expect(CrmEmail::first()->status)->toBe('sent');
});

it('lets a client book a free slot on a public booking page, creating the lead and meeting', function () {
    Carbon::setTestNow('2030-01-07 06:00:00'); // a Monday
    $page = makeBookingPage();
    expect($page['public_url'])->toEndWith('/book/discovery-call');

    asGuest();
    $this->getJson('/api/v1/public/booking/discovery-call')->assertOk()->assertJsonPath('data.company.name', $this->company->name);
    $slots = $this->getJson('/api/v1/public/booking/discovery-call/slots?date=2030-01-07')->assertOk()->json('data.slots');
    expect($slots)->toHaveCount(6)->and($slots[0])->toBe('2030-01-07T09:00:00+00:00');

    $this->postJson('/api/v1/public/booking/discovery-call', [
        'name' => 'Ada Client', 'email' => 'ada@example.test', 'phone' => '+211 900', 'notes' => 'Pricing', 'start' => $slots[0],
    ])->assertCreated();

    $meeting = CrmMeeting::query()->withoutGlobalScopes()->firstOrFail();
    $lead = CrmLead::query()->withoutGlobalScopes()->firstOrFail();
    expect($meeting->company_id)->toBe($this->company->id)
        ->and($meeting->lead_id)->toBe($lead->id)
        ->and($meeting->organizer_id)->toBe($this->admin->id)
        ->and($meeting->booking_notes)->toBe('Pricing')
        ->and($lead->source)->toBe('Booking: Discovery call')
        ->and($lead->company_id)->toBe($this->company->id);
    Mail::assertSent(CrmMeetingConfirmation::class, fn ($mail) => $mail->hasTo('ada@example.test'));

    // The slot is gone, and booking it again is refused.
    expect($this->getJson('/api/v1/public/booking/discovery-call/slots?date=2030-01-07')->json('data.slots'))->not->toContain($slots[0]);
    $this->postJson('/api/v1/public/booking/discovery-call', ['name' => 'Bob', 'email' => 'bob@example.test', 'start' => $slots[0]])
        ->assertStatus(422)->assertJsonValidationErrors('start');
});

it('hides booking pages of companies without the CRM module', function () {
    makeBookingPage();
    $this->company->update(['enabled_modules' => ['sales']]);

    asGuest();
    $this->getJson('/api/v1/public/booking/discovery-call')->assertNotFound();
});

it('turns a lead magnet sign-up into a lead and emails a working download link', function () {
    Storage::fake('local');
    $magnet = $this->post('/api/v1/crm/lead-magnets', [
        'title' => 'Pricing Guide',
        'file' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');

    asGuest();
    $this->getJson("/api/v1/public/lead-magnets/{$magnet['slug']}")->assertOk()->assertJsonPath('data.title', 'Pricing Guide');
    $url = $this->postJson("/api/v1/public/lead-magnets/{$magnet['slug']}", ['name' => 'Sam Reader', 'email' => 'Sam@Example.test'])
        ->assertCreated()->json('data.download_url');

    $lead = CrmLead::query()->withoutGlobalScopes()->firstOrFail();
    expect($lead->email)->toBe('sam@example.test')->and($lead->source)->toBe('Lead magnet: Pricing Guide');
    Mail::assertSent(CrmDirectEmail::class, fn ($mail) => $mail->hasTo('sam@example.test') && str_contains($mail->bodyText, $url));

    $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertDownload('guide.pdf');

    Sanctum::actingAs($this->admin, ['*']);
    $row = $this->getJson('/api/v1/crm/lead-magnets')->json('data.0');
    expect($row)->toMatchArray(['views_count' => 1, 'submissions_count' => 1, 'downloads_count' => 1]);
});

it('sends a campaign in batches with open, click and unsubscribe tracking', function () {
    CrmLead::create(['company_id' => $this->company->id, 'name' => 'Lee One', 'email' => 'one@example.test', 'status' => 'open']);
    CrmLead::create(['company_id' => $this->company->id, 'name' => 'Lee Dup', 'email' => 'ONE@example.test', 'status' => 'open']);
    CrmLead::create(['company_id' => $this->company->id, 'name' => 'Lee Two', 'email' => 'two@example.test', 'status' => 'open']);
    createCustomer($this->company, ['name' => 'Cus Tomer', 'email' => 'cus@example.test']);
    [$other] = createCompanyWithMainBranch();
    CrmLead::create(['company_id' => $other->id, 'name' => 'Not Ours', 'email' => 'outsider@example.test', 'status' => 'open']);

    $this->postJson('/api/v1/crm/campaigns/audience-preview', ['audience' => ['type' => 'all']])
        ->assertOk()->assertJsonPath('data.count', 3);

    $campaign = $this->postJson('/api/v1/crm/campaigns', [
        'name' => 'Launch', 'subject' => 'Hi {{first_name}}', 'body' => "Hello {{name}},\nRead https://example.test/new", 'audience' => ['type' => 'leads'],
    ])->assertCreated()->json('data');
    $this->postJson("/api/v1/crm/campaigns/{$campaign['id']}/schedule")->assertOk()->assertJsonPath('data.status', 'scheduled');

    $service = app(EmailCampaignService::class);
    expect($service->processDue(1, 0))->toBe(1);
    expect($service->processDue(10, 0))->toBe(1);
    $this->getJson("/api/v1/crm/campaigns/{$campaign['id']}")->assertJsonPath('data.status', 'sent')->assertJsonPath('data.stats.sent', 2);

    Mail::assertSent(CrmCampaignEmail::class, 2);
    $sent = null;
    Mail::assertSent(CrmCampaignEmail::class, function (CrmCampaignEmail $mail) use (&$sent) {
        if ($mail->hasTo('one@example.test')) {
            $sent = $mail;
        }

        return true;
    });
    expect($sent->subjectLine)->toBe('Hi Lee')
        ->and($sent->headers()->text['List-Unsubscribe'])->toContain('/api/v1/public/email/unsubscribe/');
    preg_match('#href="([^"]+/public/email/click/[^"]+)"#', $sent->bodyHtml, $m);
    $clickUrl = html_entity_decode($m[1]);
    $token = CrmEmailCampaignRecipient::query()->where('email', 'one@example.test')->value('token');

    asGuest();
    $this->get("/api/v1/public/email/open/{$token}")->assertOk()->assertHeader('Content-Type', 'image/gif');
    $this->get(parse_url($clickUrl, PHP_URL_PATH).'?'.parse_url($clickUrl, PHP_URL_QUERY))->assertRedirect('https://example.test/new');
    $this->get("/api/v1/public/email/click/{$token}?u=".urlencode('https://evil.test').'&s=forged')->assertNotFound();
    $this->postJson("/api/v1/public/email/unsubscribe/{$token}")->assertOk()->assertJsonPath('data.email', 'one@example.test');

    Sanctum::actingAs($this->admin, ['*']);
    $this->getJson("/api/v1/crm/campaigns/{$campaign['id']}")
        ->assertJsonPath('data.stats.opened', 1)->assertJsonPath('data.stats.clicked', 1)->assertJsonPath('data.stats.unsubscribed', 1);
    // Unsubscribed people are left out of later campaigns.
    $this->postJson('/api/v1/crm/campaigns/audience-preview', ['audience' => ['type' => 'leads']])->assertJsonPath('data.count', 1);

    $report = $this->getJson('/api/v1/crm/reports/marketing')->assertOk()->json('data');
    expect($report['campaigns'])->toMatchArray(['sent' => 2, 'opened' => 1, 'clicked' => 1, 'unsubscribed' => 1, 'open_rate' => 50]);
});

it('keeps campaigns private to their company and needs permission to send', function () {
    $campaign = $this->postJson('/api/v1/crm/campaigns', [
        'name' => 'Private', 'subject' => 'S', 'body' => 'B', 'audience' => ['type' => 'leads'],
    ])->json('data');

    [$other, $otherBranch] = createCompanyWithMainBranch();
    Sanctum::actingAs(createUserWithRole('company_admin', $other, $otherBranch), ['*']);
    $this->getJson("/api/v1/crm/campaigns/{$campaign['id']}")->assertNotFound();

    Sanctum::actingAs(createUserWithRole('cashier', $this->company, $this->branch), ['*']);
    $this->getJson('/api/v1/crm/campaigns')->assertForbidden();
    $this->postJson("/api/v1/crm/campaigns/{$campaign['id']}/schedule")->assertForbidden();
});

it('turns any typed link name into a valid web address instead of rejecting it', function () {
    Storage::fake('local');
    $magnet = $this->post('/api/v1/crm/lead-magnets', [
        'title' => 'Guide',
        'slug' => 'Price Guide 2026!',
        'file' => UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');
    expect($magnet['slug'])->toBe('price-guide-2026');

    expect(makeBookingPage(['slug' => 'Intro Call (30 min)'])['slug'])->toBe('intro-call-30-min');
});
