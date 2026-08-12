<?php

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->user = createUserWithRole('company_admin', $this->company, $this->branch);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'client_code' => $this->company->client_code,
        'email' => $this->user->email,
        'password' => 'password',
    ])->json('data.token');
});

it('lets an existing token keep working while the company stays active', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('rejects an already-issued token on the very next request after the company is suspended mid-session', function () {
    // No new login — the SAME token from beforeEach(), issued while the company was
    // still active, is reused here. This is the whole point of the middleware: a
    // suspension takes effect on the next request, not just future logins.
    $this->company->update(['status' => 'suspended', 'suspended_at' => now()]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(403);
    expect($response->json('reason'))->toBe('company_suspended');
});

it('rejects an already-issued token the same way when the company reverts to pending mid-session', function () {
    $this->company->update(['status' => 'pending']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(403);
    expect($response->json('reason'))->toBe('company_pending');
});

it('blocks every ordinary authenticated route, not just auth/me, once suspended mid-session', function () {
    $this->company->update(['status' => 'suspended', 'suspended_at' => now()]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/customers')
        ->assertStatus(403);
});

it('lets the token work again immediately after the company is reactivated', function () {
    $this->company->update(['status' => 'suspended', 'suspended_at' => now()]);
    $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/auth/me')->assertStatus(403);

    $this->company->update(['status' => 'active', 'activated_at' => now()]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});
