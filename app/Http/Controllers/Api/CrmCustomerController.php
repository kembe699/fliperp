<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Crm\CustomerVisibilityService;
use App\Services\Pos\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmCustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        protected CustomerVisibilityService $customerVisibilityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAnyInCrm', Customer::class);

        $customers = $this->customerService->paginate(
            [
                ...$request->only('customer_type', 'branch_id', 'search'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            ],
            $request->integer('per_page', 15),
            fn ($query) => $this->customerVisibilityService->scopeToVisibleCustomers($query, $request->user()),
        );

        return $this->paginated(CustomerResource::collection($customers));
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('viewInCrm', $customer);

        return $this->success(new CustomerResource($customer));
    }
}
