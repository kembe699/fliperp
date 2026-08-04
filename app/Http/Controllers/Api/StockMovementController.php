<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovement\ManualStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Services\Inventory\StockMovementService;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function __construct(protected StockMovementService $stockMovementService) {}

    public function manual(ManualStockMovementRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->manual($request->validated());

        return $this->success(new StockMovementResource($movement), 'Stock movement recorded successfully.', 201);
    }
}
