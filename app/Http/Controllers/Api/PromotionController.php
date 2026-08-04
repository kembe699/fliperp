<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\StorePromotionRequest;
use App\Http\Requests\Promotion\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\Sales\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(protected PromotionService $promotionService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $promotions = $this->promotionService->paginate($request->integer('per_page', 15));

        return $this->paginated(PromotionResource::collection($promotions));
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        $promotion = $this->promotionService->create($request->validated());

        return $this->success(new PromotionResource($promotion), 'Promotion created successfully.', 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        $this->authorize('view', $promotion);

        return $this->success(new PromotionResource($promotion->load('products')));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $this->authorize('update', $promotion);

        $promotion = $this->promotionService->update($promotion, $request->validated());

        return $this->success(new PromotionResource($promotion), 'Promotion updated successfully.');
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->authorize('delete', $promotion);

        $this->promotionService->delete($promotion);

        return $this->success(null, 'Promotion deleted successfully.');
    }
}
