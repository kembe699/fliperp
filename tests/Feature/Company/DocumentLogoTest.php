<?php

use App\Support\Brand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

// Documents show the company's own logo; without one, the FlipERP logo (plus the company name).
beforeEach(function () {
    Cache::flush();
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
});

const TINY_PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\x0f\0\0\x01\x01\x00\x05\x18\xd8N\0\0\0\0IEND\xaeB`\x82";

it('falls back to the FlipERP logo when the company has none', function () {
    $this->company->update(['logo_url' => null]);

    expect($this->company->logoDataUri())->toBeNull()
        ->and($this->company->documentLogoDataUri())->toBe(Brand::logoDataUri())
        ->and(Brand::logoDataUri())->toStartWith('data:image/png;base64,');
});

it('fetches a logo kept on the old R2 bucket, once, and caches it', function () {
    Http::fake(['https://pub-abc.r2.dev/*' => Http::response(TINY_PNG, 200, ['Content-Type' => 'image/png'])]);
    $this->company->update(['logo_url' => 'https://pub-abc.r2.dev/company/logo.png']);

    $uri = $this->company->documentLogoDataUri();
    $this->company->documentLogoDataUri();

    expect($uri)->toBe('data:image/png;base64,'.base64_encode(TINY_PNG));
    Http::assertSentCount(1);
});

it('never fetches a logo from a host that is not allowed, or over plain http', function () {
    Http::fake();
    foreach (['https://169.254.169.254/latest/meta-data', 'https://evil.example.com/logo.png', 'http://pub-abc.r2.dev/logo.png'] as $url) {
        $this->company->update(['logo_url' => $url]);
        expect($this->company->logoDataUri())->toBeNull()
            ->and($this->company->documentLogoDataUri())->toBe(Brand::logoDataUri());
    }
    Http::assertNothingSent();
});

it('ignores a remote "logo" that is not an image', function () {
    Http::fake(['https://pub-abc.r2.dev/*' => Http::response('<html>nope</html>', 200, ['Content-Type' => 'text/html'])]);
    $this->company->update(['logo_url' => 'https://pub-abc.r2.dev/company/page.html']);

    expect($this->company->logoDataUri())->toBeNull();
});

it('puts a logo in the header of a quotation PDF either way', function () {
    $customer = createCustomer($this->company);
    $product = createProduct($this->company, ['selling_price' => 100]);
    $id = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id, 'customer_id' => $customer->id, 'reference_number' => 'QUO-LOGO-1',
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->json('data.id');

    $pdf = $this->get("/api/v1/quotations/{$id}/pdf")->assertOk()->getContent();

    // Header logo + "Powered by" footer mark: at least two images in the PDF.
    expect(substr_count($pdf, '/Subtype /Image'))->toBeGreaterThanOrEqual(2);
});
