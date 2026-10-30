<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->platform, , $this->platformStaff] = createPlatformCompany();
    Sanctum::actingAs($this->platformStaff, ['*']);
    Mail::fake();

    $this->onboard = fn (string $code, string $email) => Company::find($this->postJson('/api/v1/platform-admin/clients', [
        'company_name' => "Client {$code}", 'client_code' => $code, 'admin_name' => 'A', 'admin_email' => $email,
    ])->assertCreated()->json('data.company.id'));
});

it('permanently deletes a client with its data, users and billing customer, and frees the code', function () {
    $client = ($this->onboard)('GONE-1', 'admin@gone.test');
    $other = ($this->onboard)('STAYS-1', 'admin@stays.test');
    $admin = User::withoutGlobalScope(CompanyScope::class)->where('company_id', $client->id)->first();
    $this->patchJson("/api/v1/platform-admin/clients/{$client->id}/activate")->assertOk();

    // Real records inside the client, including ones that reference each other and its user.
    Sanctum::actingAs($admin, ['*']);
    $product = createProduct($client, ['selling_price' => 100]);
    $customer = createCustomer($client);
    $this->postJson('/api/v1/invoices', [
        'customer_id' => $customer->id,
        'branch_id' => $admin->branch_id,
        'due_date' => now()->addDays(7)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->assertCreated();
    $admin->createToken('phone');

    Sanctum::actingAs($this->platformStaff, ['*']);
    $this->deleteJson("/api/v1/platform-admin/clients/{$client->id}", ['confirm_code' => 'gone-1'])->assertOk();

    expect(DB::table('companies')->where('id', $client->id)->exists())->toBeFalse();
    expect(DB::table('users')->where('id', $admin->id)->exists())->toBeFalse();
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $admin->id)->exists())->toBeFalse();
    expect(DB::table('invoices')->where('company_id', $client->id)->exists())->toBeFalse();
    expect(DB::table('products')->where('company_id', $client->id)->exists())->toBeFalse();
    expect(DB::table('customers')->where('id', $client->billing_customer_id)->exists())->toBeFalse();

    // The other client is untouched.
    expect(DB::table('companies')->where('id', $other->id)->exists())->toBeTrue();
    expect(DB::table('users')->where('company_id', $other->id)->count())->toBe(1);

    // Recorded in the platform company's audit trail.
    expect(AuditLog::withoutGlobalScopes()->where('company_id', $this->platform->id)->where('module', 'Company')
        ->where('action', 'deleted')->where('record_id', $client->id)->exists())->toBeTrue();

    // The code can be used again.
    ($this->onboard)('GONE-1', 'admin2@gone.test');
});

it('refuses without the matching client code typed as confirmation', function () {
    $client = ($this->onboard)('KEEP-1', 'admin@keep.test');

    $this->deleteJson("/api/v1/platform-admin/clients/{$client->id}", ['confirm_code' => 'KEEP-2'])
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_code');
    $this->deleteJson("/api/v1/platform-admin/clients/{$client->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_code');

    expect(DB::table('companies')->where('id', $client->id)->exists())->toBeTrue();
});

it('refuses while the platform has invoices billed to the client', function () {
    $client = ($this->onboard)('BILLED-1', 'admin@billed.test');
    $product = createProduct($this->platform, ['selling_price' => 250]);
    $this->postJson("/api/v1/platform-admin/clients/{$client->id}/invoices", [
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 250]],
    ])->assertCreated();

    $this->deleteJson("/api/v1/platform-admin/clients/{$client->id}", ['confirm_code' => 'BILLED-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('client');

    expect(DB::table('companies')->where('id', $client->id)->exists())->toBeTrue();
});

it('never deletes the platform company', function () {
    $this->deleteJson("/api/v1/platform-admin/clients/{$this->platform->id}", ['confirm_code' => $this->platform->client_code])
        ->assertNotFound();
});
