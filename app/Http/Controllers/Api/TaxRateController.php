<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxRate\StoreTaxRateRequest;
use App\Http\Requests\TaxRate\UpdateTaxRateRequest;
use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;
use App\Services\Pos\TaxRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function __construct(protected TaxRateService $taxRateService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxRate::class);

        $taxRates = $this->taxRateService->paginate($request->integer('per_page', 15));

        return $this->paginated(TaxRateResource::collection($taxRates));
    }

    public function store(StoreTaxRateRequest $request): JsonResponse
    {
        $this->authorize('create', TaxRate::class);

        $taxRate = $this->taxRateService->create($request->validated());

        return $this->success(new TaxRateResource($taxRate), 'Tax rate created successfully.', 201);
    }

    public function show(TaxRate $taxRate): JsonResponse
    {
        $this->authorize('view', $taxRate);

        return $this->success(new TaxRateResource($taxRate));
    }

    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate): JsonResponse
    {
        $this->authorize('update', $taxRate);

        $taxRate = $this->taxRateService->update($taxRate, $request->validated());

        return $this->success(new TaxRateResource($taxRate), 'Tax rate updated successfully.');
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $this->authorize('delete', $taxRate);

        $this->taxRateService->delete($taxRate);

        return $this->success(null, 'Tax rate deleted successfully.');
    }
}
