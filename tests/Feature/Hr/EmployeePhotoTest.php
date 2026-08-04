<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->employee = createEmployee($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('uploads an employee photo and stores its public URL', function () {
    $file = UploadedFile::fake()->create('photo.png', 200, 'image/png');

    $response = $this->postJson("/api/v1/employees/{$this->employee->id}/photo", ['image' => $file]);

    $response->assertOk();
    $photoUrl = $response->json('data.photo_url');
    expect($photoUrl)->toContain('/storage/employees/');

    $this->assertDatabaseHas('employees', ['id' => $this->employee->id, 'photo_url' => $photoUrl]);

    $path = str($photoUrl)->after('/storage/')->toString();
    Storage::disk('public')->assertExists($path);
});

it('rejects a non-image file for the employee photo', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->postJson("/api/v1/employees/{$this->employee->id}/photo", ['image' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('deletes the employee photo and clears photo_url', function () {
    $photoUrl = $this->postJson("/api/v1/employees/{$this->employee->id}/photo", [
        'image' => UploadedFile::fake()->create('photo.png', 200, 'image/png'),
    ])->json('data.photo_url');
    $path = str($photoUrl)->after('/storage/')->toString();

    $response = $this->deleteJson("/api/v1/employees/{$this->employee->id}/photo");

    $response->assertOk();
    expect($response->json('data.photo_url'))->toBeNull();
    $this->assertDatabaseHas('employees', ['id' => $this->employee->id, 'photo_url' => null]);
    Storage::disk('public')->assertMissing($path);
});

it('denies uploading a photo for an employee belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherEmployee = createEmployee($otherCompany, $otherBranch);

    $this->postJson("/api/v1/employees/{$otherEmployee->id}/photo", [
        'image' => UploadedFile::fake()->create('photo.png', 200, 'image/png'),
    ])->assertStatus(404);
});
