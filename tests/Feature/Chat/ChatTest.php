<?php

use App\Models\ChatConversation;
use App\Models\ChatMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->alice = createUserWithRole('cashier', $this->company, $this->branch);
    $this->bob = createUserWithRole('branch_manager', $this->company, $this->branch);
});

it('is idempotent: starting a conversation with the same user twice returns the same conversation', function () {
    Sanctum::actingAs($this->alice, ['*']);

    $first = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->assertOk()->json('data.id');
    $second = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->assertOk()->json('data.id');

    expect($second)->toBe($first);
    expect(ChatConversation::count())->toBe(1);
});

it('also returns the same conversation when the other participant initiates it from their side', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $fromAlice = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    Sanctum::actingAs($this->bob, ['*']);
    $fromBob = $this->postJson("/api/v1/chat/conversations/with/{$this->alice->id}")->json('data.id');

    expect($fromBob)->toBe($fromAlice);
    expect(ChatConversation::count())->toBe(1);
});

it('rejects starting a conversation with a user in a different company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $stranger = createUserWithRole('cashier', $otherCompany, $otherBranch);

    Sanctum::actingAs($this->alice, ['*']);

    $this->postJson("/api/v1/chat/conversations/with/{$stranger->id}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('sends a text message and delivers it through the conversation history for both participants', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'Hey Bob'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Hey Bob')
        ->assertJsonPath('data.sender_id', $this->alice->id);

    Sanctum::actingAs($this->bob, ['*']);
    $messages = $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")->assertOk()->json('data');

    expect($messages)->toHaveCount(1);
    expect($messages[0]['body'])->toBe('Hey Bob');
});

it('sends a message with a file attachment and returns a working download path', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $file = UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'See attached',
        'attachments' => [$file],
    ])->assertCreated();

    $attachments = $response->json('data.attachments');
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['file_name'])->toBe('quote.pdf');

    $attachment = ChatMessageAttachment::findOrFail($attachments[0]['id']);
    Storage::disk('public')->assertExists($attachment->file_path);

    // download_path is deliberately relative (the frontend hits it through
    // its authenticated axios instance) — prepend the API prefix ourselves here.
    $this->get('/api/v1'.$attachments[0]['download_path'])->assertOk();
});

it('rejects a message with neither text nor an attachment', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('blocks a non-participant from reading or posting to a conversation', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $outsider = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($outsider, ['*']);

    $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")->assertStatus(403);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'sneaky'])->assertStatus(403);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/read")->assertStatus(403);
});

it('blocks a user from a different company from reading a conversation, even if they guess the id', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $stranger = createUserWithRole('cashier', $otherCompany, $otherBranch);
    Sanctum::actingAs($stranger, ['*']);

    // 404, not 403: ChatConversation's TenantModel CompanyScope means route
    // model binding can't even find a row belonging to a different company,
    // so it never reaches the participant-authorization check at all —
    // arguably better, since it doesn't confirm the id exists in any company.
    $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")->assertStatus(404);
});

it('enforces attachment download authorization to conversation participants only', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $file = UploadedFile::fake()->create('secret.pdf', 50, 'application/pdf');
    $downloadPath = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'attachments' => [$file],
    ])->json('data.attachments.0.download_path');

    // The other participant can download it.
    Sanctum::actingAs($this->bob, ['*']);
    $this->get('/api/v1'.$downloadPath)->assertOk();

    // A non-participant in the same company cannot.
    $outsider = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($outsider, ['*']);
    $this->get('/api/v1'.$downloadPath)->assertStatus(403);
});

it('marks a conversation as read and reports 0 unread afterwards', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'Ping'])->assertCreated();

    Sanctum::actingAs($this->bob, ['*']);
    $conversations = $this->getJson('/api/v1/chat/conversations')->assertOk()->json('data');
    expect(collect($conversations)->firstWhere('id', $conversationId)['unread_count'])->toBe(1);

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/read")->assertOk();

    $conversations = $this->getJson('/api/v1/chat/conversations')->assertOk()->json('data');
    expect(collect($conversations)->firstWhere('id', $conversationId)['unread_count'])->toBe(0);
});

it('authorizes the private-user broadcast channel only for the matching user id', function () {
    // Channel-auth signing is pure local HMAC (Pusher protocol) with no
    // network round trip, so the real broadcaster can run here safely even
    // without Reverb running — unlike actually publishing an event, which
    // needs a live server and is why the rest of the suite runs on 'log'.
    // channels.php must be (re-)required AFTER switching the default
    // connection: Broadcast::channel() registers on whichever connection
    // instance is current at call time, and withBroadcasting()'s own
    // booted()-callback loading never fires during Pest's bootstrap here.
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
    Sanctum::actingAs($this->alice, ['*']);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-user.{$this->alice->id}",
    ])->assertOk();

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-user.{$this->bob->id}",
    ])->assertStatus(403);
});

it('authorizes the presence-company channel only for members of that company', function () {
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
    Sanctum::actingAs($this->alice, ['*']);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-company.{$this->company->id}",
    ])->assertOk();

    [$otherCompany] = createCompanyWithMainBranch();

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-company.{$otherCompany->id}",
    ])->assertStatus(403);
});

it('authorizes the private-conversation channel only for its participants', function () {
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-conversation.{$conversationId}",
    ])->assertOk();

    $outsider = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($outsider, ['*']);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-conversation.{$conversationId}",
    ])->assertStatus(403);
});
