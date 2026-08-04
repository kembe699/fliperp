<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\ProductService;
use Illuminate\Http\JsonResponse;

class ProductVariantController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(ProductVariantResource::collection($product->variants));
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $variant = $this->productService->createVariant($product, $request->validated());

        return $this->success(new ProductVariantResource($variant), 'Product variant created successfully.', 201);
    }

    public function show(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(new ProductVariantResource($variant));
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        $variant = $this->productService->updateVariant($variant, $request->validated());

        return $this->success(new ProductVariantResource($variant), 'Product variant updated successfully.');
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        $this->productService->deleteVariant($variant);

        return $this->success(null, 'Product variant deleted successfully.');
    }
}
