<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentType\StorePaymentTypeRequest;
use App\Http\Requests\PaymentType\UpdatePaymentTypeRequest;
use App\Http\Resources\PaymentTypeResource;
use App\Models\PaymentType;
use App\Services\Pos\PaymentTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    public function __construct(protected PaymentTypeService $paymentTypeService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentType::class);

        $paymentTypes = $this->paymentTypeService->paginate($request->integer('per_page', 15));

        return $this->paginated(PaymentTypeResource::collection($paymentTypes));
    }

    public function store(StorePaymentTypeRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentType::class);

        $paymentType = $this->paymentTypeService->create($request->validated());

        return $this->success(new PaymentTypeResource($paymentType), 'Payment type created successfully.', 201);
    }

    public function show(PaymentType $paymentType): JsonResponse
    {
        $this->authorize('view', $paymentType);

        return $this->success(new PaymentTypeResource($paymentType));
    }

    public function update(UpdatePaymentTypeRequest $request, PaymentType $paymentType): JsonResponse
    {
        $this->authorize('update', $paymentType);

        $paymentType = $this->paymentTypeService->update($paymentType, $request->validated());

        return $this->success(new PaymentTypeResource($paymentType), 'Payment type updated successfully.');
    }

    public function destroy(PaymentType $paymentType): JsonResponse
    {
        $this->authorize('delete', $paymentType);

        $this->paymentTypeService->delete($paymentType);

        return $this->success(null, 'Payment type deleted successfully.');
    }
}
