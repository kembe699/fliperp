<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PriceList\StorePriceListItemRequest;
use App\Http\Requests\PriceList\UpdatePriceListItemRequest;
use App\Http\Resources\PriceListItemResource;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Services\Sales\PriceListService;
use Illuminate\Http\JsonResponse;

class PriceListItemController extends Controller
{
    public function __construct(protected PriceListService $priceListService) {}

    public function index(PriceList $priceList): JsonResponse
    {
        $this->authorize('view', $priceList);

        return $this->success(PriceListItemResource::collection($priceList->items));
    }

    public function store(StorePriceListItemRequest $request, PriceList $priceList): JsonResponse
    {
        $this->authorize('update', $priceList);

        $item = $this->priceListService->addItem($priceList, $request->validated());

        return $this->success(new PriceListItemResource($item), 'Price list item added successfully.', 201);
    }

    public function show(PriceList $priceList, PriceListItem $item): JsonResponse
    {
        $this->authorize('view', $priceList);

        return $this->success(new PriceListItemResource($item));
    }

    public function update(UpdatePriceListItemRequest $request, PriceList $priceList, PriceListItem $item): JsonResponse
    {
        $this->authorize('update', $priceList);

        $item = $this->priceListService->updateItem($item, $request->validated());

        return $this->success(new PriceListItemResource($item), 'Price list item updated successfully.');
    }

    public function destroy(PriceList $priceList, PriceListItem $item): JsonResponse
    {
        $this->authorize('update', $priceList);

        $this->priceListService->deleteItem($item);

        return $this->success(null, 'Price list item deleted successfully.');
    }
}
