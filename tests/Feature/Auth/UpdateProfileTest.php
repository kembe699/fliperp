<?php

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->user = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($this->user, ['*']);
});

it('updates name, email and phone without touching the password', function () {
    $response = $this->putJson('/api/v1/auth/profile', [
        'name' => 'Updated Name',
        'email' => 'updated@demo.test',
        'phone' => '555-9999',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.name', 'Updated Name')
        ->assertJsonPath('data.user.email', 'updated@demo.test')
        ->assertJsonPath('data.user.phone', '555-9999');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

it('changes the password when the current password is correct', function () {
    $response = $this->putJson('/api/v1/auth/profile', [
        'current_password' => 'password',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertOk();
    expect(Hash::check('NewPassword1', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function () {
    $response = $this->putJson('/api/v1/auth/profile', [
        'current_password' => 'wrong-password',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('current_password');
    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

it('requires the current password when changing the password', function () {
    $this->putJson('/api/v1/auth/profile', [
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');
});

it('rejects a new password that fails complexity requirements', function () {
    $this->putJson('/api/v1/auth/profile', [
        'current_password' => 'password',
        'password' => 'alllowercase',
        'password_confirmation' => 'alllowercase',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('rejects an email already taken by another user in the same company', function () {
    $other = createUserWithRole('cashier', $this->company, $this->branch);

    $this->putJson('/api/v1/auth/profile', ['email' => $other->email])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('lets the user keep their own current email unchanged', function () {
    $this->putJson('/api/v1/auth/profile', ['email' => $this->user->email, 'name' => 'Same Email Guy'])
        ->assertOk()
        ->assertJsonPath('data.user.email', $this->user->email);
});
