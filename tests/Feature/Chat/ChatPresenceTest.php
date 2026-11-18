<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Presence without a WebSocket server: GET chat/people checks the caller in and
// lists the company's people, online = seen in the last 90 seconds.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->alice = createUserWithRole('cashier', $this->company, $this->branch);
    $this->bob = createUserWithRole('branch_manager', $this->company, $this->branch);
});

afterEach(fn () => Carbon::setTestNow());

it('marks the caller as seen and shows another user as online once they check in', function () {
    Sanctum::actingAs($this->alice, ['*']);
    $people = $this->getJson('/api/v1/chat/people')->assertOk()->json('data');
    expect($people)->toHaveCount(1)
        ->and($people[0])->toMatchArray(['id' => $this->bob->id, 'online' => false])
        ->and($this->alice->fresh()->last_seen_at)->not->toBeNull();

    Sanctum::actingAs($this->bob, ['*']);
    $this->getJson('/api/v1/chat/people')->assertOk();

    Sanctum::actingAs($this->alice, ['*']);
    expect($this->getJson('/api/v1/chat/people')->json('data.0'))->toMatchArray(['id' => $this->bob->id, 'online' => true]);
});

it('shows someone as offline once they have not checked in for over 90 seconds', function () {
    Sanctum::actingAs($this->bob, ['*']);
    $this->getJson('/api/v1/chat/people')->assertOk();

    Carbon::setTestNow(now()->addSeconds(120));
    Sanctum::actingAs($this->alice, ['*']);
    expect($this->getJson('/api/v1/chat/people')->json('data.0.online'))->toBeFalse();
});

it('lists only active people from the caller\'s own company, online first', function () {
    $carol = createUserWithRole('cashier', $this->company, $this->branch);
    $inactive = createUserWithRole('cashier', $this->company, $this->branch);
    $inactive->update(['is_active' => false]);
    [$other, $otherBranch] = createCompanyWithMainBranch();
    $outsider = createUserWithRole('cashier', $other, $otherBranch);
    DB::table('users')->whereIn('id', [$carol->id, $outsider->id])->update(['last_seen_at' => now()]);

    Sanctum::actingAs($this->alice, ['*']);
    $people = collect($this->getJson('/api/v1/chat/people')->json('data'));

    expect($people->pluck('id')->all())->toBe([$carol->id, $this->bob->id])
        ->and($people->first()['online'])->toBeTrue();
});

it('does not write an audit log entry or change updated_at on check-in', function () {
    $updatedAt = $this->alice->updated_at;
    $auditsBefore = AuditLog::withoutGlobalScopes()->count();
    Carbon::setTestNow(now()->addMinutes(5));

    Sanctum::actingAs($this->alice, ['*']);
    $this->getJson('/api/v1/chat/people')->assertOk();

    $fresh = User::find($this->alice->id);
    expect($fresh->last_seen_at)->not->toBeNull()
        ->and($fresh->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(AuditLog::withoutGlobalScopes()->count())->toBe($auditsBefore);
});

it('requires sign-in', function () {
    $this->getJson('/api/v1/chat/people')->assertUnauthorized();
});

it('still sends the message when the WebSocket server cannot be reached', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'k',
        'broadcasting.connections.reverb.secret' => 's',
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1, // nothing listens here
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);
    Sanctum::actingAs($this->alice, ['*']);
    $conversationId = $this->postJson("/api/v1/chat/conversations/with/{$this->bob->id}")->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'Still delivered'])->assertCreated();

    Sanctum::actingAs($this->bob, ['*']);
    expect(collect($this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")->json('data'))->pluck('body')->all())->toBe(['Still delivered']);
});
