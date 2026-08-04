<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PriceList\StorePriceListRequest;
use App\Http\Requests\PriceList\UpdatePriceListRequest;
use App\Http\Resources\PriceListResource;
use App\Models\PriceList;
use App\Services\Sales\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct(protected PriceListService $priceListService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PriceList::class);

        $priceLists = $this->priceListService->paginate($request->integer('per_page', 15));

        return $this->paginated(PriceListResource::collection($priceLists));
    }

    public function store(StorePriceListRequest $request): JsonResponse
    {
        $this->authorize('create', PriceList::class);

        $priceList = $this->priceListService->create($request->validated());

        return $this->success(new PriceListResource($priceList), 'Price list created successfully.', 201);
    }

    public function show(PriceList $priceList): JsonResponse
    {
        $this->authorize('view', $priceList);

        return $this->success(new PriceListResource($priceList->load('items')));
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): JsonResponse
    {
        $this->authorize('update', $priceList);

        $priceList = $this->priceListService->update($priceList, $request->validated());

        return $this->success(new PriceListResource($priceList), 'Price list updated successfully.');
    }

    public function destroy(PriceList $priceList): JsonResponse
    {
        $this->authorize('delete', $priceList);

        $this->priceListService->delete($priceList);

        return $this->success(null, 'Price list deleted successfully.');
    }
}
