<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockLevelResource;
use App\Services\Inventory\StockLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockLevelController extends Controller
{
    public function __construct(protected StockLevelService $stockLevelService) {}

    public function index(Request $request): JsonResponse
    {
        $levels = $this->stockLevelService->paginate([
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'product_id' => $request->integer('product_id') ?: null,
            'low_stock' => $request->boolean('low_stock'),
        ], $request->integer('per_page', 15));

        return $this->paginated(StockLevelResource::collection($levels));
    }
}
