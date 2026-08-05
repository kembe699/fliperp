<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->staffA = createUserWithRole('branch_manager', $this->company, $this->branch);
    $this->staffB = createUserWithRole('branch_manager', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);

    Sanctum::actingAs($this->admin, ['*']);
});

it('assigns a primary owner to a customer', function () {
    $response = $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->staffA->id,
        'role' => 'primary',
    ])->assertCreated();

    expect($response->json('data.role'))->toBe('primary');
    expect($response->json('data.unassigned_at'))->toBeNull();
});

it('unassigns the previous primary owner when a new one is assigned', function () {
    $first = $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->staffA->id,
        'role' => 'primary',
    ])->json('data');

    $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->staffB->id,
        'role' => 'primary',
    ])->assertCreated();

    $previous = $this->getJson("/api/v1/crm/account-assignments/{$first['id']}")->json('data');
    expect($previous['unassigned_at'])->not->toBeNull();

    $current = $this->getJson('/api/v1/crm/account-assignments/current?customer_id='.$this->customer->id)->json('data');
    $primaries = collect($current)->where('role', 'primary');
    expect($primaries)->toHaveCount(1);
    expect($primaries->first()['user_id'])->toBe($this->staffB->id);
});

it('allows multiple concurrent active support assignments alongside one primary', function () {
    $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->admin->id,
        'role' => 'primary',
    ])->assertCreated();

    $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->staffA->id,
        'role' => 'support',
    ])->assertCreated();

    $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->staffB->id,
        'role' => 'support',
    ])->assertCreated();

    $current = $this->getJson('/api/v1/crm/account-assignments/current?customer_id='.$this->customer->id)->json('data');
    expect($current)->toHaveCount(3);
});

it('explicitly unassigns a support staff member without touching the primary', function () {
    $primary = $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->admin->id,
        'role' => 'primary',
    ])->json('data');

    $support = $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id,
        'user_id' => $this->staffA->id,
        'role' => 'support',
    ])->json('data');

    $this->postJson("/api/v1/crm/account-assignments/{$support['id']}/unassign")
        ->assertOk()
        ->assertJsonPath('data.unassigned_at', fn ($value) => $value !== null);

    $current = $this->getJson('/api/v1/crm/account-assignments/current?customer_id='.$this->customer->id)->json('data');
    expect(collect($current)->pluck('id')->all())->toBe([$primary['id']]);
});
