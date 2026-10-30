<?php

use App\Models\Company;
use App\Support\Modules;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->platform, , $this->platformStaff] = createPlatformCompany();
    Mail::fake();

    [$this->client, $this->branch] = createCompanyWithMainBranch();
    $this->clientAdmin = createUserWithRole('company_admin', $this->client, $this->branch);
});

it('gives a client every module until the platform says otherwise', function () {
    Sanctum::actingAs($this->platformStaff, ['*']);

    $data = $this->getJson("/api/v1/platform-admin/clients/{$this->client->id}")->assertOk()->json('data');

    expect($data['company']['enabled_modules'])->toBe(Modules::keys())
        ->and(collect($data['modules'])->pluck('key')->all())->toBe(Modules::keys());

    Sanctum::actingAs($this->clientAdmin, ['*']);
    $this->getJson('/api/v1/grants')->assertOk();
});

it('takes a switched-off module away from the client, in the API and in their permissions', function () {
    Sanctum::actingAs($this->platformStaff, ['*']);
    $this->patchJson("/api/v1/platform-admin/clients/{$this->client->id}", ['enabled_modules' => ['sales', 'accounting']])
        ->assertOk()
        ->assertJsonPath('data.enabled_modules', ['sales', 'accounting']);

    $this->clientAdmin->refresh();
    Sanctum::actingAs($this->clientAdmin, ['*']);

    $this->getJson('/api/v1/grants')->assertForbidden();
    $this->getJson('/api/v1/purchase-orders')->assertForbidden();
    $this->getJson('/api/v1/employees')->assertForbidden();
    // Products serve POS too, so they stay while Sales is on.
    $this->getJson('/api/v1/products')->assertOk();
    $this->getJson('/api/v1/invoices')->assertOk();

    $permissions = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.permissions');
    expect($permissions)->toContain('invoices.view', 'products.view', 'chart-of-accounts.view', 'users.view')
        ->not->toContain('grants.view', 'purchase-orders.view', 'employees.view', 'stock-transfers.view');
});

it('turns a module back on', function () {
    $this->client->update(['enabled_modules' => ['sales']]);
    Sanctum::actingAs($this->platformStaff, ['*']);
    $this->patchJson("/api/v1/platform-admin/clients/{$this->client->id}", ['enabled_modules' => ['sales', 'grants']])->assertOk();

    $this->clientAdmin->refresh();
    Sanctum::actingAs($this->clientAdmin, ['*']);
    $this->getJson('/api/v1/grants')->assertOk();
});

it('onboards a client with only the modules chosen', function () {
    Sanctum::actingAs($this->platformStaff, ['*']);

    expect($this->getJson('/api/v1/platform-admin/modules')->assertOk()->json('data'))->toHaveCount(count(Modules::ALL));

    $this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Small NGO', 'client_code' => 'SNGO', 'admin_name' => 'A', 'admin_email' => 'a@sngo.test',
        'enabled_modules' => ['grants', 'accounting'],
    ])->assertCreated()->assertJsonPath('data.company.enabled_modules', ['grants', 'accounting']);

    $this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => 'Bad', 'client_code' => 'BAD', 'admin_name' => 'A', 'admin_email' => 'a@bad.test',
        'enabled_modules' => ['spaceship'],
    ])->assertStatus(422)->assertJsonValidationErrors('enabled_modules.0');

    expect(Company::where('client_code', 'SNGO')->value('enabled_modules'))->toBe(['grants', 'accounting']);
});
