<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPayment\StoreCustomerPaymentRequest;
use App\Http\Requests\CustomerPayment\UpdateCustomerPaymentRequest;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\CustomerPayment;
use App\Services\Sales\CustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPaymentController extends Controller
{
    public function __construct(protected CustomerPaymentService $customerPaymentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerPayment::class);

        $payments = $this->customerPaymentService->paginate($request->integer('per_page', 15));

        return $this->paginated(CustomerPaymentResource::collection($payments));
    }

    public function store(StoreCustomerPaymentRequest $request): JsonResponse
    {
        $this->authorize('create', CustomerPayment::class);

        $payment = $this->customerPaymentService->create($request->validated());

        return $this->success(new CustomerPaymentResource($payment), 'Customer payment recorded successfully.', 201);
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('view', $customerPayment);

        return $this->success(new CustomerPaymentResource($customerPayment));
    }

    public function update(UpdateCustomerPaymentRequest $request, CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('update', $customerPayment);

        $customerPayment = $this->customerPaymentService->update($customerPayment, $request->validated());

        return $this->success(new CustomerPaymentResource($customerPayment), 'Customer payment updated successfully.');
    }

    public function destroy(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('delete', $customerPayment);

        $this->customerPaymentService->delete($customerPayment);

        return $this->success(null, 'Customer payment deleted successfully.');
    }
}
