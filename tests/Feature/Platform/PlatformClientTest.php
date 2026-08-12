<?php

use App\Mail\ClientWelcomeEmail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->platform, $this->platformBranch, $this->platformStaff] = createPlatformCompany();
    Sanctum::actingAs($this->platformStaff, ['*']);
});

it('creates a client company, its branch, a billing customer under the platform company, and an admin user — all in one transaction', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Acme Logistics',
        'admin_name' => 'Acme Admin',
        'admin_email' => 'admin@acmelogistics.test',
        'branch_name' => 'Head Office',
    ]);

    $response->assertCreated();
    expect($response->json('data.company.status'))->toBe('pending');
    expect($response->json('data.company.client_code'))->toStartWith('NHC-');
    expect($response->json('data.temp_password'))->not->toBeEmpty();

    $companyId = $response->json('data.company.id');

    $this->assertDatabaseHas('companies', ['id' => $companyId, 'name' => 'Acme Logistics', 'status' => 'pending', 'is_platform' => false]);
    $this->assertDatabaseHas('branches', ['company_id' => $companyId, 'is_main' => true, 'name' => 'Head Office']);
    $this->assertDatabaseHas('users', ['company_id' => $companyId, 'email' => 'admin@acmelogistics.test']);

    $company = Company::find($companyId);
    expect($company->billing_customer_id)->not->toBeNull();
    // The billing customer lives under the PLATFORM company, not the new client.
    $this->assertDatabaseHas('customers', ['id' => $company->billing_customer_id, 'company_id' => $this->platform->id, 'name' => 'Acme Logistics']);

    $admin = User::withoutGlobalScopes()->where('email', 'admin@acmelogistics.test')->first();
    expect($admin->hasRole('company_admin'))->toBeTrue();

    Mail::assertSent(ClientWelcomeEmail::class, fn ($mail) => $mail->hasTo('admin@acmelogistics.test') && $mail->clientCode === $company->client_code);
});

it('generates a unique client_code for every client created', function () {
    Mail::fake();

    $codes = collect(range(1, 5))->map(function (int $i) {
        return $this->postJson('/api/v1/platform-admin/clients', [
            'company_name' => "Client {$i}",
            'admin_name' => "Admin {$i}",
            'admin_email' => "admin{$i}@client-uniq.test",
        ])->json('data.company.client_code');
    });

    expect($codes->unique())->toHaveCount(5);
});

it('lists only non-platform clients, filterable by status and searchable by name/client_code', function () {
    Mail::fake();
    $pending = $this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Searchable Co', 'admin_name' => 'A', 'admin_email' => 'a@searchable.test',
    ])->json('data.company');

    $response = $this->getJson('/api/v1/platform-admin/clients?search=Searchable');
    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($pending['id']);
    expect(collect($response->json('data'))->pluck('is_platform'))->each->toBeFalse();

    $activeOnly = $this->getJson('/api/v1/platform-admin/clients?status=active');
    expect(collect($activeOnly->json('data'))->pluck('id'))->not->toContain($pending['id']);
});

it('suspends and reactivates a client, timestamping the transition', function () {
    Mail::fake();
    $client = Company::find($this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Suspend Me Ltd', 'admin_name' => 'A', 'admin_email' => 'a@suspendme.test',
    ])->json('data.company.id'));
    $client->update(['status' => 'active']);

    $suspend = $this->patchJson("/api/v1/platform-admin/clients/{$client->id}/suspend");
    $suspend->assertOk();
    expect($suspend->json('data.status'))->toBe('suspended');
    expect($suspend->json('data.suspended_at'))->not->toBeNull();

    $activate = $this->patchJson("/api/v1/platform-admin/clients/{$client->id}/activate");
    $activate->assertOk();
    expect($activate->json('data.status'))->toBe('active');
    expect($activate->json('data.activated_at'))->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', ['record_id' => $client->id, 'user_id' => $this->platformStaff->id, 'action' => 'updated']);
});

it('returns full client detail: admin users, billing summary, and tickets', function () {
    Mail::fake();
    $client = Company::find($this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Detail Co', 'admin_name' => 'Detail Admin', 'admin_email' => 'admin@detailco.test',
    ])->json('data.company.id'));

    $response = $this->getJson("/api/v1/platform-admin/clients/{$client->id}");

    $response->assertOk();
    expect($response->json('data.company.name'))->toBe('Detail Co');
    expect(collect($response->json('data.admin_users'))->pluck('email'))->toContain('admin@detailco.test');
    expect((float) $response->json('data.billing.total_invoiced'))->toBe(0.0);
    expect($response->json('data.tickets'))->toBe([]);
});

it('allows a platform_staff user to access every /platform-admin/* route and see all clients data', function () {
    Mail::fake();
    [$clientA] = createCompanyWithMainBranch();
    [$clientB] = createCompanyWithMainBranch();

    $response = $this->getJson('/api/v1/platform-admin/clients');
    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($clientA->id);
    expect($ids)->toContain($clientB->id);
});

it('blocks a normal user — even a company_admin of their own tenant — from /platform-admin/* entirely', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $regularAdmin = createUserWithRole('company_admin', $company, $branch);
    Sanctum::actingAs($regularAdmin, ['*']);

    $this->getJson('/api/v1/platform-admin/clients')->assertStatus(403);
    $this->postJson('/api/v1/platform-admin/clients', ['company_name' => 'x'])->assertStatus(403);
});

it('blocks a user with is_platform_staff=false even if somehow granted platform-clients permissions directly', function () {
    [$company, $branch] = createCompanyWithMainBranch();
    $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'is_platform_staff' => false]);
    $user->givePermissionTo(['platform-clients.view', 'platform-clients.create']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/platform-admin/clients')->assertStatus(403);
});
