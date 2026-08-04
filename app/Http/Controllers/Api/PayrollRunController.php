<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollRun\StorePayrollRunRequest;
use App\Http\Resources\PayrollRunResource;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollRun;
use App\Services\Hr\PayrollRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollRunController extends Controller
{
    public function __construct(protected PayrollRunService $payrollRunService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $payrollRuns = $this->payrollRunService->paginate($request->integer('per_page', 15));

        return $this->paginated(PayrollRunResource::collection($payrollRuns));
    }

    public function store(StorePayrollRunRequest $request): JsonResponse
    {
        $this->authorize('create', PayrollRun::class);

        $payrollRun = $this->payrollRunService->create($request->validated());

        return $this->success(new PayrollRunResource($payrollRun), 'Payroll run created successfully.', 201);
    }

    public function show(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        return $this->success(new PayrollRunResource($payrollRun));
    }

    public function process(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('process', $payrollRun);

        $payrollRun = $this->payrollRunService->process($payrollRun);

        return $this->success(new PayrollRunResource($payrollRun), 'Payroll run processed successfully.');
    }

    public function payslips(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        return $this->success(PayslipResource::collection($payrollRun->payslips));
    }
}
