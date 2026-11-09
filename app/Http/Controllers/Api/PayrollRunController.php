<?php

namespace App\Http\Controllers\Api;

use App\Exports\PayrollRunExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollRun\StorePayrollRunRequest;
use App\Http\Resources\PayrollRunResource;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Hr\PayrollRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        return $this->success(PayslipResource::collection($payrollRun->payslips()->with('employee.department', 'paidBy', 'paymentType', 'paymentAccount')->get()));
    }

    /** Pay one employee's salary from this run. */
    public function pay(Request $request, PayrollRun $payrollRun, Payslip $payslip): JsonResponse
    {
        $this->authorize('pay', $payrollRun);
        abort_unless($payslip->payroll_run_id === $payrollRun->id, 404);

        $payslip = $this->payrollRunService->payPayslip($payslip, $this->paymentData($request));

        return $this->success(new PayslipResource($payslip->load('employee.department')), 'Salary paid.');
    }

    /** Pay everyone in the run who hasn't been paid yet. */
    public function payAll(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('pay', $payrollRun);

        $count = $this->payrollRunService->payAll($payrollRun, $this->paymentData($request));

        return $this->success(['paid' => $count], $count === 1 ? '1 salary paid.' : "{$count} salaries paid.");
    }

    /** The run as an Excel file: one row per employee, deductions split out, who was paid. */
    public function export(PayrollRun $payrollRun): BinaryFileResponse
    {
        $this->authorize('view', $payrollRun);

        $name = 'payroll-'.substr((string) $payrollRun->period_start, 0, 10).'-to-'.substr((string) $payrollRun->period_end, 0, 10).'.xlsx';

        return Excel::download(new PayrollRunExport($payrollRun), $name);
    }

    protected function paymentData(Request $request): array
    {
        $companyId = $request->user()->company_id;

        return $request->validate([
            'payment_type_id' => ['nullable', Rule::exists('payment_types', 'id')->where('company_id', $companyId)],
            'account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)->where('type', 'asset')],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);
    }
}
