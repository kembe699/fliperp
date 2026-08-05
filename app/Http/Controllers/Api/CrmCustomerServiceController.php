<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmCustomerService\StoreCrmCustomerServiceRequest;
use App\Http\Requests\CrmCustomerService\UpdateCrmCustomerServiceRequest;
use App\Http\Resources\CrmCustomerServiceResource;
use App\Models\Customer;
use App\Models\CrmCustomerService;
use App\Services\Crm\CustomerServiceRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmCustomerServiceController extends Controller
{
    public function __construct(protected CustomerServiceRecordService $customerServiceRecordService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmCustomerService::class);

        $records = $this->customerServiceRecordService->paginate(
            $request->integer('customer_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmCustomerServiceResource::collection($records));
    }

    public function store(StoreCrmCustomerServiceRequest $request): JsonResponse
    {
        $this->authorize('create', CrmCustomerService::class);

        $record = $this->customerServiceRecordService->create($request->validated());

        return $this->success(new CrmCustomerServiceResource($record), 'Customer service logged successfully.', 201);
    }

    public function show(CrmCustomerService $crmCustomerService): JsonResponse
    {
        $this->authorize('view', $crmCustomerService);

        return $this->success(new CrmCustomerServiceResource($crmCustomerService->load(['service', 'deal'])));
    }

    public function update(UpdateCrmCustomerServiceRequest $request, CrmCustomerService $crmCustomerService): JsonResponse
    {
        $this->authorize('update', $crmCustomerService);

        $record = $this->customerServiceRecordService->update($crmCustomerService, $request->validated());

        return $this->success(new CrmCustomerServiceResource($record), 'Customer service updated successfully.');
    }

    public function destroy(CrmCustomerService $crmCustomerService): JsonResponse
    {
        $this->authorize('delete', $crmCustomerService);

        $this->customerServiceRecordService->delete($crmCustomerService);

        return $this->success(null, 'Customer service deleted successfully.');
    }

    public function serviceStatement(Customer $customer): JsonResponse
    {
        $this->authorize('viewInCrm', $customer);

        return $this->success($this->customerServiceRecordService->statement($customer));
    }
}
