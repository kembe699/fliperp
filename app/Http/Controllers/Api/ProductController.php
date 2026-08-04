<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductImageRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->paginate(
            [
                ...$request->only('category_id', 'search'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
                'low_stock' => $request->boolean('low_stock'),
            ],
            $request->integer('per_page', 15),
        );

        return $this->paginated(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create($request->validated());

        return $this->success(new ProductResource($product), 'Product created successfully.', 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(new ProductResource($product->load('variants')));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->update($product, $request->validated());

        return $this->success(new ProductResource($product), 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return $this->success(null, 'Product deleted successfully.');
    }

    public function uploadImage(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->uploadImage($product, $request->file('image'));

        return $this->success(new ProductResource($product), 'Product image uploaded successfully.');
    }

    public function deleteImage(Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->deleteImage($product);

        return $this->success(new ProductResource($product), 'Product image removed successfully.');
    }

    public function stockMovements(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $movements = $product->stockMovements()
            ->with('warehouse')
            ->latest('moved_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(StockMovementResource::collection($movements));
    }
}
