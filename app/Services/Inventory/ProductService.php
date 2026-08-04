<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\Media\ImageUploadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(protected ImageUploadService $imageUploadService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id))
            ->when(($filters['is_active'] ?? null) !== null, fn (Builder $query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['low_stock'] ?? null, fn (Builder $query) => $query->whereHas(
                'stockLevels',
                fn (Builder $stockQuery) => $stockQuery->whereColumn('stock_levels.quantity_on_hand', '<=', 'products.reorder_level'),
            ))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%")
            ))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function delete(Product $product): void
    {
        if (StockMovement::where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'product' => ['Cannot delete a product that has stock movements. Deactivate it instead.'],
            ]);
        }

        $product->delete();
    }

    public function uploadImage(Product $product, UploadedFile $file): Product
    {
        $url = $this->imageUploadService->replace($file, 'products', $product->image_url);
        $product->update(['image_url' => $url]);

        return $product;
    }

    public function deleteImage(Product $product): Product
    {
        $this->imageUploadService->delete($product->image_url);
        $product->update(['image_url' => null]);

        return $product;
    }

    public function createVariant(Product $product, array $data): ProductVariant
    {
        return $product->variants()->create($data);
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update($data);

        return $variant;
    }

    public function deleteVariant(ProductVariant $variant): void
    {
        if (StockMovement::where('product_variant_id', $variant->id)->exists()) {
            throw ValidationException::withMessages([
                'variant' => ['Cannot delete a variant that has stock movements.'],
            ]);
        }

        $variant->delete();
    }
}
