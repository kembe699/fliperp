<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('uploads a company logo and stores its public URL', function () {
    $file = UploadedFile::fake()->create('logo.png', 200, 'image/png');

    $response = $this->postJson('/api/v1/company/logo', ['image' => $file]);

    $response->assertOk();
    $logoUrl = $response->json('data.logo_url');
    expect($logoUrl)->toContain('/storage/company/');

    $this->assertDatabaseHas('companies', ['id' => $this->company->id, 'logo_url' => $logoUrl]);

    $path = str($logoUrl)->after('/storage/')->toString();
    Storage::disk('public')->assertExists($path);
});

it('rejects a non-image file for the company logo', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->postJson('/api/v1/company/logo', ['image' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('deletes the company logo and clears logo_url', function () {
    $logoUrl = $this->postJson('/api/v1/company/logo', [
        'image' => UploadedFile::fake()->create('logo.png', 200, 'image/png'),
    ])->json('data.logo_url');
    $path = str($logoUrl)->after('/storage/')->toString();

    $response = $this->deleteJson('/api/v1/company/logo');

    $response->assertOk();
    expect($response->json('data.logo_url'))->toBeNull();
    $this->assertDatabaseHas('companies', ['id' => $this->company->id, 'logo_url' => null]);
    Storage::disk('public')->assertMissing($path);
});

it('embeds the companys own uploaded logo as a data URI, for PDF rendering', function () {
    $this->postJson('/api/v1/company/logo', [
        'image' => UploadedFile::fake()->create('logo.png', 200, 'image/png'),
    ])->assertOk();

    $dataUri = $this->company->fresh()->logoDataUri();

    expect($dataUri)->not->toBeNull();
    expect($dataUri)->toStartWith('data:');
    expect($dataUri)->toContain(';base64,');
});

it('returns null (not a bundled fallback logo) for logoDataUri when the company has no logo', function () {
    expect($this->company->logoDataUri())->toBeNull();
});

it('returns null for logoDataUri when logo_url does not point at our own public storage', function () {
    $this->company->update(['logo_url' => 'https://example.com/some-external-image.png']);

    expect($this->company->fresh()->logoDataUri())->toBeNull();
});

it('denies a super_admin with no single company from using the convenience logo endpoint', function () {
    $superAdmin = createUserWithRole('super_admin');
    // createUserWithRole() runs while $this->admin (company-scoped) is still the
    // active Auth user, and User::booted()'s creating hook auto-inherits the
    // current auth's company_id when none is given — force it back to null here.
    $superAdmin->update(['company_id' => null]);
    Sanctum::actingAs($superAdmin, ['*']);

    $this->postJson('/api/v1/company/logo', [
        'image' => UploadedFile::fake()->create('logo.png', 200, 'image/png'),
    ])->assertStatus(422);
});
