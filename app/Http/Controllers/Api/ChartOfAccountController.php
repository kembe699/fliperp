<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccount\StoreChartOfAccountRequest;
use App\Http\Requests\ChartOfAccount\UpdateChartOfAccountRequest;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\ChartOfAccount;
use App\Services\Finance\ChartOfAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function __construct(protected ChartOfAccountService $chartOfAccountService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $accounts = $this->chartOfAccountService->paginate($request->integer('per_page', 15));

        return $this->paginated(ChartOfAccountResource::collection($accounts));
    }

    public function store(StoreChartOfAccountRequest $request): JsonResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $account = $this->chartOfAccountService->create($request->validated());

        return $this->success(new ChartOfAccountResource($account), 'Chart of account created successfully.', 201);
    }

    public function seed(): JsonResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $accounts = $this->chartOfAccountService->seedDefaults();

        return $this->success(ChartOfAccountResource::collection($accounts), 'Default chart of accounts seeded.');
    }

    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        $this->authorize('view', $chartOfAccount);

        return $this->success(new ChartOfAccountResource($chartOfAccount));
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $this->authorize('update', $chartOfAccount);

        $chartOfAccount = $this->chartOfAccountService->update($chartOfAccount, $request->validated());

        return $this->success(new ChartOfAccountResource($chartOfAccount), 'Chart of account updated successfully.');
    }

    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        $this->authorize('delete', $chartOfAccount);

        $this->chartOfAccountService->delete($chartOfAccount);

        return $this->success(null, 'Chart of account deleted successfully.');
    }
}
