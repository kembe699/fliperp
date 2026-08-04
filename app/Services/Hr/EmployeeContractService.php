<?php

namespace App\Services\Hr;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\Media\ImageUploadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeContractService
{
    public function __construct(protected ImageUploadService $imageUploadService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return EmployeeContract::query()
            ->whereHas('employee')
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): EmployeeContract
    {
        $contractBody = $data['contract_body'] ?? null;

        if (! $contractBody && empty($data['document_url'])) {
            $employee = Employee::findOrFail($data['employee_id']);
            $contractBody = $this->generateTemplate($employee, $data);
        }

        return EmployeeContract::create([
            'employee_id' => $data['employee_id'],
            'contract_type' => $data['contract_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'base_salary' => $data['base_salary'],
            'currency_code' => $data['currency_code'] ?? 'USD',
            'document_url' => $data['document_url'] ?? null,
            'contract_body' => $contractBody,
            'status' => 'draft',
        ]);
    }

    public function update(EmployeeContract $contract, array $data): EmployeeContract
    {
        if (isset($data['status']) && $data['status'] !== $contract->status) {
            if (! ($contract->status === 'draft' && $data['status'] === 'generated')) {
                throw ValidationException::withMessages([
                    'status' => ['A contract can only move from draft to generated here; signing has its own endpoint.'],
                ]);
            }
        }

        $contract->update(collect($data)->only([
            'contract_type', 'start_date', 'end_date', 'base_salary', 'currency_code', 'contract_body', 'status',
        ])->toArray());

        return $contract;
    }

    public function delete(EmployeeContract $contract): void
    {
        $contract->delete();
    }

    public function uploadDocument(EmployeeContract $contract, UploadedFile $file, bool $isSignedPhysicalCopy, ?string $signedByName = null): EmployeeContract
    {
        $url = $this->imageUploadService->replace($file, 'employee-contracts', $contract->document_url);

        $updates = ['document_url' => $url];

        if ($isSignedPhysicalCopy) {
            $updates['status'] = 'signed';
            $updates['signed_at'] = now();
            $updates['signed_by_name'] = $signedByName ?? $contract->signed_by_name;
        }

        $contract->update($updates);

        return $contract;
    }

    public function sign(EmployeeContract $contract, string $signatureData, string $signedByName): EmployeeContract
    {
        if ($contract->status === 'signed') {
            throw ValidationException::withMessages([
                'status' => ['This contract has already been signed.'],
            ]);
        }

        $contract->update([
            'signature_data' => $signatureData,
            'signed_by_name' => $signedByName,
            'signed_at' => now(),
            'status' => 'signed',
        ]);

        $pdf = $this->renderPdf($contract->fresh(['employee']));
        $path = 'employee-contracts/contract-'.$contract->id.'-'.Str::random(10).'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $contract->update(['document_url' => Storage::disk('public')->url($path)]);

        return $contract->fresh();
    }

    public function renderPdf(EmployeeContract $contract)
    {
        $employee = $contract->employee;
        $company = Company::find($employee->company_id);

        return Pdf::loadView('pdf.employee-contract', [
            'contract' => $contract,
            'employee' => $employee,
            'company' => $company,
        ])->setPaper('a4', 'portrait');
    }

    protected function generateTemplate(Employee $employee, array $data): string
    {
        $company = Company::find($employee->company_id);
        $companyName = $company->name ?? 'the Company';
        $employeeName = trim($employee->first_name.' '.$employee->last_name);
        $position = $employee->position?->title ?? 'the assigned role';
        $department = $employee->department?->name ?? 'the assigned department';
        $startDate = \Illuminate\Support\Carbon::parse($data['start_date'])->format('d F Y');
        $endDate = ! empty($data['end_date']) ? \Illuminate\Support\Carbon::parse($data['end_date'])->format('d F Y') : null;
        $currency = $data['currency_code'] ?? 'USD';
        $salary = number_format((float) $data['base_salary'], 2);
        $contractType = $data['contract_type'] ?? 'Employment';

        $termClause = $endDate
            ? "This Agreement shall remain in effect until {$endDate}, unless terminated earlier in accordance with its terms."
            : 'This Agreement shall remain in effect until terminated by either party in accordance with its terms and applicable law.';

        return <<<HTML
<p style="text-align:center;"><strong>{$contractType} CONTRACT</strong></p>
<p>This Employment Contract ("Agreement") is entered into between <strong>{$companyName}</strong> ("Employer") and <strong>{$employeeName}</strong> ("Employee").</p>
<p><strong>1. Position</strong><br>The Employee is employed in the position of <strong>{$position}</strong> in the <strong>{$department}</strong> department.</p>
<p><strong>2. Commencement Date</strong><br>This Agreement commences on <strong>{$startDate}</strong>.</p>
<p><strong>3. Compensation</strong><br>The Employee shall receive a base salary of <strong>{$currency} {$salary}</strong> per annum, payable in accordance with the Employer's standard payroll schedule.</p>
<p><strong>4. Term</strong><br>{$termClause}</p>
<p><strong>5. General</strong><br>This Agreement is governed by applicable employment law and constitutes the entire understanding between the parties regarding the Employee's employment.</p>
HTML;
    }
}
