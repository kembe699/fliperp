<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRate\StoreExchangeRateRequest;
use App\Http\Requests\ExchangeRate\UpdateExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Services\Finance\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function __construct(protected ExchangeRateService $exchangeRateService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $rates = $this->exchangeRateService->paginate($request->only('currency_code'), $request->integer('per_page', 15));

        return $this->paginated(ExchangeRateResource::collection($rates));
    }

    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $this->authorize('create', ExchangeRate::class);

        $rate = $this->exchangeRateService->create($request->validated());

        return $this->success(new ExchangeRateResource($rate), 'Exchange rate created successfully.', 201);
    }

    public function show(ExchangeRate $exchangeRate): JsonResponse
    {
        $this->authorize('view', $exchangeRate);

        return $this->success(new ExchangeRateResource($exchangeRate));
    }

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $this->authorize('update', $exchangeRate);

        $rate = $this->exchangeRateService->update($exchangeRate, $request->validated());

        return $this->success(new ExchangeRateResource($rate), 'Exchange rate updated successfully.');
    }

    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        $this->authorize('delete', $exchangeRate);

        $this->exchangeRateService->delete($exchangeRate);

        return $this->success(null, 'Exchange rate deleted successfully.');
    }

    /**
     * Any signed-in user of the company — every document form looks up the
     * default rate for its date, not only users who manage rates.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3'],
            'date' => ['required', 'date'],
        ]);

        $companyId = $request->user()->company_id;
        $base = $this->exchangeRateService->baseCurrency($companyId);
        $code = strtoupper($validated['currency_code']);

        if ($code === $base) {
            return $this->success(['currency_code' => $code, 'base_currency' => $base, 'is_base' => true, 'rate' => 1.0, 'effective_date' => null]);
        }

        $rate = $this->exchangeRateService->findRate($companyId, $code, $validated['date']);

        return $this->success([
            'currency_code' => $code,
            'base_currency' => $base,
            'is_base' => false,
            'rate' => $rate ? (float) $rate->rate : null,
            'effective_date' => $rate?->effective_date?->toDateString(),
        ]);
    }

    public function currencies(): JsonResponse
    {
        return $this->success($this->exchangeRateService->ratedCurrencies());
    }
}
