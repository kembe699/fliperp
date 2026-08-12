<?php

use App\Models\Company;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->user = createUserWithRole('company_admin', $this->company, $this->branch);
});

it('requires client_code on login', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('client_code');
});

it('logs in successfully with the correct client_code, email and password', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'client_code' => $this->company->client_code,
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', $this->user->email)
        ->assertJsonPath('data.company.client_code', $this->company->client_code);
});

it('rejects an unknown client_code with a generic message, not revealing whether it exists', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'client_code' => 'NHC-DOESNOTEXIST',
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.client_code.0'))->toBe('The provided credentials are incorrect.');
});

it('rejects the right client_code with an email from a DIFFERENT company, same generic message', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherUser = createUserWithRole('company_admin', $otherCompany, $otherBranch);

    $response = $this->postJson('/api/v1/auth/login', [
        'client_code' => $this->company->client_code,
        'email' => $otherUser->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.client_code.0'))->toBe('The provided credentials are incorrect.');
});

it('rejects the right client_code and email with the wrong password, same generic message', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'client_code' => $this->company->client_code,
        'email' => $this->user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.client_code.0'))->toBe('The provided credentials are incorrect.');
});

it('blocks login for a suspended company with a clear, distinct message', function () {
    $this->company->update(['status' => 'suspended', 'suspended_at' => now()]);

    $response = $this->postJson('/api/v1/auth/login', [
        'client_code' => $this->company->client_code,
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.client_code.0'))->toContain('suspended');
});

it('blocks login for a pending company with a clear, distinct message', function () {
    $this->company->update(['status' => 'pending']);

    $response = $this->postJson('/api/v1/auth/login', [
        'client_code' => $this->company->client_code,
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.client_code.0'))->toContain('not yet active');
});
