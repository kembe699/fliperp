<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grant\StoreGrantBudgetLineRequest;
use App\Http\Requests\Grant\StoreGrantDeaRequest;
use App\Http\Requests\Grant\StoreGrantRequest;
use App\Http\Requests\Grant\UpdateGrantRequest;
use App\Http\Resources\GrantBudgetLineResource;
use App\Http\Resources\GrantDeaResource;
use App\Http\Resources\GrantResource;
use App\Models\Grant;
use App\Models\GrantBudgetLine;
use App\Models\GrantDea;
use App\Services\Grants\GrantReportService;
use App\Services\Grants\GrantService;
use App\Services\Reporting\ReportCurrencyConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SOFs (grants), with their DEAs and donor budget lines, and the SOF Grant Report. */
class GrantController extends Controller
{
    public function __construct(
        protected GrantService $grantService,
        protected GrantReportService $grantReportService,
        protected ReportCurrencyConverter $currencyConverter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Grant::class);

        $grants = $this->grantService->paginate($request->only('donor_id', 'status', 'search'), $request->integer('per_page', 15));

        return $this->paginated(GrantResource::collection($grants));
    }

    public function store(StoreGrantRequest $request): JsonResponse
    {
        $this->authorize('create', Grant::class);

        return $this->success(new GrantResource($this->grantService->create($request->validated())->load('donor')), 'SOF created successfully.', 201);
    }

    public function show(Grant $grant): JsonResponse
    {
        $this->authorize('view', $grant);

        return $this->success(new GrantResource($grant->load(['donor', 'deas', 'budgetLines.account', 'budgetLines.dea'])));
    }

    public function update(UpdateGrantRequest $request, Grant $grant): JsonResponse
    {
        $this->authorize('update', $grant);

        return $this->success(new GrantResource($this->grantService->update($grant, $request->validated())), 'SOF updated successfully.');
    }

    public function destroy(Grant $grant): JsonResponse
    {
        $this->authorize('delete', $grant);

        $this->grantService->delete($grant);

        return $this->success(null, 'SOF deleted successfully.');
    }

    /** Award, spend and burn for every SOF. */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Grant::class);

        return $this->success($this->grantReportService->summary($request->user()->company_id));
    }

    /**
     * The SOF Grant Report. `currency` re-expresses the base-currency columns
     * like any report; the SOF-currency columns always stay in the SOF's own.
     */
    public function report(Request $request, Grant $grant): JsonResponse
    {
        $this->authorize('view', $grant);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $data = $this->grantReportService->generate($grant, $validated['from'] ?? null, $validated['to'] ?? null);

        return $this->success($this->currencyConverter->convert($data, $grant->company_id, $validated['currency'] ?? null, $data['to']));
    }

    public function storeDea(StoreGrantDeaRequest $request, Grant $grant): JsonResponse
    {
        $this->authorize('update', $grant);

        return $this->success(new GrantDeaResource($this->grantService->createDea($grant, $request->validated())), 'DEA added successfully.', 201);
    }

    public function updateDea(StoreGrantDeaRequest $request, GrantDea $dea): JsonResponse
    {
        $this->authorize('update', $dea->grant);

        return $this->success(new GrantDeaResource($this->grantService->updateDea($dea, $request->validated())), 'DEA updated successfully.');
    }

    public function destroyDea(GrantDea $dea): JsonResponse
    {
        $this->authorize('update', $dea->grant);

        $this->grantService->deleteDea($dea);

        return $this->success(null, 'DEA deleted successfully.');
    }

    public function storeBudgetLine(StoreGrantBudgetLineRequest $request, Grant $grant): JsonResponse
    {
        $this->authorize('update', $grant);

        $line = $this->grantService->saveBudgetLine($grant, $request->validated());

        return $this->success(new GrantBudgetLineResource($line->load(['account', 'dea'])), 'Budget line saved successfully.', 201);
    }

    public function destroyBudgetLine(GrantBudgetLine $budgetLine): JsonResponse
    {
        $this->authorize('update', $budgetLine->grant);

        $this->grantService->deleteBudgetLine($budgetLine);

        return $this->success(null, 'Budget line deleted successfully.');
    }
}
