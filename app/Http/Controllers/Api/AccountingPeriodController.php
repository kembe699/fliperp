<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingPeriod\StoreAccountingPeriodRequest;
use App\Http\Requests\AccountingPeriod\UpdateAccountingPeriodRequest;
use App\Http\Resources\AccountingPeriodResource;
use App\Models\AccountingPeriod;
use App\Services\Finance\AccountingPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function __construct(protected AccountingPeriodService $accountingPeriodService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountingPeriod::class);

        $periods = $this->accountingPeriodService->paginate($request->integer('per_page', 15));

        return $this->paginated(AccountingPeriodResource::collection($periods));
    }

    public function store(StoreAccountingPeriodRequest $request): JsonResponse
    {
        $this->authorize('create', AccountingPeriod::class);

        $period = $this->accountingPeriodService->create($request->validated());

        return $this->success(new AccountingPeriodResource($period), 'Accounting period created successfully.', 201);
    }

    public function show(AccountingPeriod $accountingPeriod): JsonResponse
    {
        $this->authorize('view', $accountingPeriod);

        return $this->success(new AccountingPeriodResource($accountingPeriod));
    }

    public function update(UpdateAccountingPeriodRequest $request, AccountingPeriod $accountingPeriod): JsonResponse
    {
        $this->authorize('update', $accountingPeriod);

        $accountingPeriod = $this->accountingPeriodService->update($accountingPeriod, $request->validated());

        return $this->success(new AccountingPeriodResource($accountingPeriod), 'Accounting period updated successfully.');
    }

    public function destroy(AccountingPeriod $accountingPeriod): JsonResponse
    {
        $this->authorize('delete', $accountingPeriod);

        $this->accountingPeriodService->delete($accountingPeriod);

        return $this->success(null, 'Accounting period deleted successfully.');
    }

    public function close(AccountingPeriod $accountingPeriod): JsonResponse
    {
        $this->authorize('close', $accountingPeriod);

        $accountingPeriod = $this->accountingPeriodService->close($accountingPeriod);

        return $this->success(new AccountingPeriodResource($accountingPeriod), 'Accounting period closed successfully.');
    }
}
