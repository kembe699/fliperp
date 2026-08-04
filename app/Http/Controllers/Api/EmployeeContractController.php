<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeContract\SignEmployeeContractRequest;
use App\Http\Requests\EmployeeContract\StoreEmployeeContractDocumentRequest;
use App\Http\Requests\EmployeeContract\StoreEmployeeContractRequest;
use App\Http\Requests\EmployeeContract\UpdateEmployeeContractRequest;
use App\Http\Resources\EmployeeContractResource;
use App\Models\EmployeeContract;
use App\Services\Hr\EmployeeContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeContractController extends Controller
{
    public function __construct(protected EmployeeContractService $employeeContractService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmployeeContract::class);

        $contracts = $this->employeeContractService->paginate($request->only('employee_id'), $request->integer('per_page', 15));

        return $this->paginated(EmployeeContractResource::collection($contracts));
    }

    public function store(StoreEmployeeContractRequest $request): JsonResponse
    {
        $this->authorize('create', EmployeeContract::class);

        $contract = $this->employeeContractService->create($request->validated());

        return $this->success(new EmployeeContractResource($contract), 'Employee contract created successfully.', 201);
    }

    public function show(EmployeeContract $employeeContract): JsonResponse
    {
        $this->authorize('view', $employeeContract);

        return $this->success(new EmployeeContractResource($employeeContract));
    }

    public function update(UpdateEmployeeContractRequest $request, EmployeeContract $employeeContract): JsonResponse
    {
        $this->authorize('update', $employeeContract);

        $employeeContract = $this->employeeContractService->update($employeeContract, $request->validated());

        return $this->success(new EmployeeContractResource($employeeContract), 'Employee contract updated successfully.');
    }

    public function destroy(EmployeeContract $employeeContract): JsonResponse
    {
        $this->authorize('delete', $employeeContract);

        $this->employeeContractService->delete($employeeContract);

        return $this->success(null, 'Employee contract deleted successfully.');
    }

    public function uploadDocument(StoreEmployeeContractDocumentRequest $request, EmployeeContract $employeeContract): JsonResponse
    {
        $this->authorize('update', $employeeContract);

        $employeeContract = $this->employeeContractService->uploadDocument(
            $employeeContract,
            $request->file('document'),
            $request->boolean('is_signed_physical_copy'),
            $request->string('signed_by_name')->toString() ?: null,
        );

        return $this->success(new EmployeeContractResource($employeeContract), 'Contract document uploaded successfully.');
    }

    public function sign(SignEmployeeContractRequest $request, EmployeeContract $employeeContract): JsonResponse
    {
        $this->authorize('update', $employeeContract);

        $employeeContract = $this->employeeContractService->sign(
            $employeeContract,
            $request->validated('signature_data'),
            $request->validated('signed_by_name'),
        );

        return $this->success(new EmployeeContractResource($employeeContract), 'Contract signed successfully.');
    }

    public function pdf(Request $request, EmployeeContract $employeeContract): Response
    {
        $this->authorize('view', $employeeContract);

        $pdf = $this->employeeContractService->renderPdf($employeeContract->load('employee'));

        return $this->pdfResponse($pdf, "contract-{$employeeContract->id}.pdf", $request->boolean('download'));
    }
}
