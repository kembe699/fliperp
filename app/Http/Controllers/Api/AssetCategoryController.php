<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetCategory\StoreAssetCategoryRequest;
use App\Http\Requests\AssetCategory\UpdateAssetCategoryRequest;
use App\Http\Resources\AssetCategoryResource;
use App\Models\AssetCategory;
use App\Services\Assets\AssetCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    public function __construct(protected AssetCategoryService $assetCategoryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetCategory::class);

        $categories = $this->assetCategoryService->paginate($request->integer('per_page', 15));

        return $this->paginated(AssetCategoryResource::collection($categories));
    }

    public function store(StoreAssetCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', AssetCategory::class);

        $category = $this->assetCategoryService->create($request->validated());

        return $this->success(new AssetCategoryResource($category), 'Asset category created successfully.', 201);
    }

    public function show(AssetCategory $assetCategory): JsonResponse
    {
        $this->authorize('view', $assetCategory);

        return $this->success(new AssetCategoryResource($assetCategory));
    }

    public function update(UpdateAssetCategoryRequest $request, AssetCategory $assetCategory): JsonResponse
    {
        $this->authorize('update', $assetCategory);

        $assetCategory = $this->assetCategoryService->update($assetCategory, $request->validated());

        return $this->success(new AssetCategoryResource($assetCategory), 'Asset category updated successfully.');
    }

    public function destroy(AssetCategory $assetCategory): JsonResponse
    {
        $this->authorize('delete', $assetCategory);

        $this->assetCategoryService->delete($assetCategory);

        return $this->success(null, 'Asset category deleted successfully.');
    }
}
