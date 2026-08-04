<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->product = createProduct($this->company);

    Sanctum::actingAs($this->admin, ['*']);
});

it('uploads a product image and stores its public URL', function () {
    $file = UploadedFile::fake()->create('product.jpg', 500, 'image/jpeg');

    $response = $this->postJson("/api/v1/products/{$this->product->id}/image", ['image' => $file]);

    $response->assertOk();
    $imageUrl = $response->json('data.image_url');
    expect($imageUrl)->toContain('/storage/products/');

    $this->assertDatabaseHas('products', ['id' => $this->product->id, 'image_url' => $imageUrl]);

    $path = str($imageUrl)->after('/storage/')->toString();
    Storage::disk('public')->assertExists($path);
});

it('replaces the previous stored file when a new image is uploaded', function () {
    $first = $this->postJson("/api/v1/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->create('first.jpg', 200, 'image/jpeg'),
    ])->json('data.image_url');
    $firstPath = str($first)->after('/storage/')->toString();

    $second = $this->postJson("/api/v1/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->create('second.jpg', 200, 'image/jpeg'),
    ])->json('data.image_url');
    $secondPath = str($second)->after('/storage/')->toString();

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

it('rejects a non-image file upload', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->postJson("/api/v1/products/{$this->product->id}/image", ['image' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('rejects a file larger than 2MB', function () {
    $file = UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg');

    $this->postJson("/api/v1/products/{$this->product->id}/image", ['image' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('deletes the stored image file and clears image_url', function () {
    $imageUrl = $this->postJson("/api/v1/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->create('product.jpg', 200, 'image/jpeg'),
    ])->json('data.image_url');
    $path = str($imageUrl)->after('/storage/')->toString();

    $response = $this->deleteJson("/api/v1/products/{$this->product->id}/image");

    $response->assertOk();
    expect($response->json('data.image_url'))->toBeNull();
    $this->assertDatabaseHas('products', ['id' => $this->product->id, 'image_url' => null]);
    Storage::disk('public')->assertMissing($path);
});

it('leaves an externally linked image_url untouched when deleting since we do not own that file', function () {
    $this->product->update(['image_url' => 'https://example.com/some-image.jpg']);

    $response = $this->deleteJson("/api/v1/products/{$this->product->id}/image");

    $response->assertOk();
    expect($response->json('data.image_url'))->toBeNull();
});

it('sets image_url directly via the normal product update endpoint for external links', function () {
    $response = $this->putJson("/api/v1/products/{$this->product->id}", [
        'image_url' => 'https://example.com/product-photo.jpg',
    ]);

    $response->assertOk()->assertJsonPath('data.image_url', 'https://example.com/product-photo.jpg');
});
