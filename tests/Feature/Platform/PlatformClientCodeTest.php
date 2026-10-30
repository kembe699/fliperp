<?php

use App\Models\Company;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->platform, , $this->platformStaff] = createPlatformCompany();
    Sanctum::actingAs($this->platformStaff, ['*']);
    Mail::fake();

    $this->onboard = fn (array $overrides = []) => $this->postJson('/api/v1/platform-admin/clients', array_merge([
        'company_name' => 'Save the Children', 'client_code' => 'SCI-SSD', 'admin_name' => 'A', 'admin_email' => 'a@sci.test',
    ], $overrides));
});

it('onboards a client with the client code the platform admin typed', function () {
    $response = ($this->onboard)(['client_code' => '  sci-ssd ']);

    $response->assertCreated();
    expect($response->json('data.company.client_code'))->toBe('SCI-SSD');
    expect(Company::where('client_code', 'SCI-SSD')->exists())->toBeTrue();
});

it('lets the new client log in with the typed code, in any case', function () {
    $onboarded = ($this->onboard)();
    $this->patchJson("/api/v1/platform-admin/clients/{$onboarded->json('data.company.id')}/activate")->assertOk();
    $password = $onboarded->json('data.temp_password');

    auth()->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['client_code' => 'sci-ssd', 'email' => 'a@sci.test', 'password' => $password])
        ->assertOk();
});

it('requires a client code', function () {
    ($this->onboard)(['client_code' => ''])->assertUnprocessable()->assertJsonValidationErrors('client_code');
});

it('rejects a client code with spaces or symbols', function (string $code) {
    ($this->onboard)(['client_code' => $code])->assertUnprocessable()->assertJsonValidationErrors('client_code');
})->with(['SC I', 'SCI_SSD', 'SCI--SSD', '-SCI', 'AB']);

it('rejects a client code another client already has, whatever the case', function () {
    ($this->onboard)()->assertCreated();

    ($this->onboard)(['client_code' => 'sci-ssd', 'admin_email' => 'b@sci.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('client_code');
});

it('never reissues the code of a deleted client', function () {
    Company::find(($this->onboard)()->json('data.company.id'))->delete();

    ($this->onboard)(['admin_email' => 'b@sci.test'])->assertUnprocessable()->assertJsonValidationErrors('client_code');
});
