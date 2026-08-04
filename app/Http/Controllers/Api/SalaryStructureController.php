<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryStructure\StoreSalaryStructureRequest;
use App\Http\Requests\SalaryStructure\UpdateSalaryStructureRequest;
use App\Http\Resources\SalaryStructureResource;
use App\Models\SalaryStructure;
use App\Services\Hr\SalaryStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function __construct(protected SalaryStructureService $salaryStructureService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalaryStructure::class);

        $salaryStructures = $this->salaryStructureService->paginate($request->only('employee_id'), $request->integer('per_page', 15));

        return $this->paginated(SalaryStructureResource::collection($salaryStructures));
    }

    public function store(StoreSalaryStructureRequest $request): JsonResponse
    {
        $this->authorize('create', SalaryStructure::class);

        $salaryStructure = $this->salaryStructureService->create($request->validated());

        return $this->success(new SalaryStructureResource($salaryStructure), 'Salary structure created successfully.', 201);
    }

    public function show(SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('view', $salaryStructure);

        return $this->success(new SalaryStructureResource($salaryStructure));
    }

    public function update(UpdateSalaryStructureRequest $request, SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('update', $salaryStructure);

        $salaryStructure = $this->salaryStructureService->update($salaryStructure, $request->validated());

        return $this->success(new SalaryStructureResource($salaryStructure), 'Salary structure updated successfully.');
    }

    public function destroy(SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('delete', $salaryStructure);

        $this->salaryStructureService->delete($salaryStructure);

        return $this->success(null, 'Salary structure deleted successfully.');
    }
}
