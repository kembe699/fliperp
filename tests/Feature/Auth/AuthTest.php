<?php

use App\Models\Company;
use App\Services\Auth\AuthService;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;

it('logs in successfully with valid credentials', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $user = createUserWithRole('company_admin', $company, $branch);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure(['data' => ['token', 'user', 'company', 'roles', 'permissions']]);
});

it('fails to log in with invalid credentials', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $user = createUserWithRole('company_admin', $company, $branch);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('logs out and revokes the token', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $user = createUserWithRole('company_admin', $company, $branch);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.token');

    expect(PersonalAccessToken::count())->toBe(1);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PersonalAccessToken::count())->toBe(0);
});

it('returns the authenticated user on the me endpoint', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $user = createUserWithRole('company_admin', $company, $branch);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.roles.0', 'company_admin');
});

it('registers a company with a main branch and admin user in one transaction', function () {
    $response = $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Acme Corp',
        'admin_name' => 'Acme Admin',
        'admin_email' => 'admin@acme.test',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response->assertCreated()->assertJsonPath('success', true);

    $this->assertDatabaseHas('companies', ['name' => 'Acme Corp']);
    $this->assertDatabaseHas('branches', ['name' => 'Main Branch', 'is_main' => true]);
    $this->assertDatabaseHas('users', ['email' => 'admin@acme.test']);
});

it('rolls back the whole transaction when register-company fails partway through', function () {
    Role::where('name', 'company_admin')->delete();

    $companiesBefore = Company::count();

    expect(fn () => app(AuthService::class)->registerCompany([
        'company_name' => 'Broken Corp',
        'admin_name' => 'Broken Admin',
        'admin_email' => 'broken@corp.test',
        'admin_password' => 'password123',
    ]))->toThrow(RoleDoesNotExist::class);

    expect(Company::count())->toBe($companiesBefore);
    $this->assertDatabaseMissing('companies', ['name' => 'Broken Corp']);
    $this->assertDatabaseMissing('users', ['email' => 'broken@corp.test']);
});
