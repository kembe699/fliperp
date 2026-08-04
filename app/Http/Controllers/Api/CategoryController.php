<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Inventory\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->categoryService->paginate($request->integer('per_page', 15));

        return $this->paginated(CategoryResource::collection($categories));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categoryService->create($request->validated());

        return $this->success(new CategoryResource($category), 'Category created successfully.', 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return $this->success(new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->categoryService->update($category, $request->validated());

        return $this->success(new CategoryResource($category), 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->delete($category);

        return $this->success(null, 'Category deleted successfully.');
    }
}
