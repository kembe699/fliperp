<?php

namespace App\Services\Hr;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\StatutoryDeductionRule;
use App\Services\Finance\JournalEntryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRunService
{
    public const SALARY_EXPENSE_ACCOUNT_CODE = '5000';

    public const STATUTORY_PAYABLE_ACCOUNT_CODE = '2100';

    public const NET_SALARIES_PAYABLE_ACCOUNT_CODE = '2200';

    public function __construct(protected JournalEntryService $journalEntryService) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PayrollRun::query()->latest('period_start')->paginate($perPage);
    }

    public function create(array $data): PayrollRun
    {
        return PayrollRun::create([
            'branch_id' => $data['branch_id'] ?? null,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => 'draft',
        ]);
    }

    public function process(PayrollRun $payrollRun): PayrollRun
    {
        if ($payrollRun->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft payroll runs can be processed.'],
            ]);
        }

        return DB::transaction(function () use ($payrollRun) {
            $employees = Employee::query()
                ->where('status', 'active')
                ->when($payrollRun->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->get();

            $rules = StatutoryDeductionRule::query()->where('is_active', true)->get();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;

            foreach ($employees as $employee) {
                $salaryStructure = $employee->currentSalaryStructure();

                if (! $salaryStructure) {
                    continue;
                }

                $allowances = collect($salaryStructure->allowances ?? [])->sum();
                $grossPay = round((float) $salaryStructure->basic_salary + $allowances, 2);

                $breakdown = [
                    'basic_salary' => (float) $salaryStructure->basic_salary,
                    'allowances' => $salaryStructure->allowances ?? [],
                    'deductions' => [],
                ];

                $deductionTotal = 0.0;
                foreach ($rules as $rule) {
                    $amount = $this->calculateDeduction($rule, $grossPay);
                    if ($amount > 0) {
                        $breakdown['deductions'][$rule->name] = $amount;
                        $deductionTotal += $amount;
                    }
                }

                $netPay = round($grossPay - $deductionTotal, 2);

                Payslip::create([
                    'payroll_run_id' => $payrollRun->id,
                    'employee_id' => $employee->id,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $deductionTotal,
                    'net_pay' => $netPay,
                    'breakdown' => $breakdown,
                ]);

                $totalGross += $grossPay;
                $totalDeductions += $deductionTotal;
                $totalNet += $netPay;
            }

            $journalEntry = $this->postPayrollJournal($payrollRun, $totalGross, $totalDeductions, $totalNet);

            $payrollRun->update([
                'status' => 'processed',
                'processed_by' => Auth::id(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $payrollRun->fresh(['payslips', 'journalEntry.lines']);
        });
    }

    protected function postPayrollJournal(PayrollRun $payrollRun, float $totalGross, float $totalDeductions, float $totalNet)
    {
        $companyId = $payrollRun->company_id;

        $salaryExpense = $this->resolveAccount($companyId, self::SALARY_EXPENSE_ACCOUNT_CODE);
        $statutoryPayable = $this->resolveAccount($companyId, self::STATUTORY_PAYABLE_ACCOUNT_CODE);
        $netSalariesPayable = $this->resolveAccount($companyId, self::NET_SALARIES_PAYABLE_ACCOUNT_CODE);

        $lines = [
            ['account_id' => $salaryExpense->id, 'debit' => $totalGross, 'credit' => 0, 'description' => 'Gross salaries expense'],
        ];

        if ($totalDeductions > 0) {
            $lines[] = ['account_id' => $statutoryPayable->id, 'debit' => 0, 'credit' => $totalDeductions, 'description' => 'Statutory deductions payable'];
        }

        $lines[] = ['account_id' => $netSalariesPayable->id, 'debit' => 0, 'credit' => $totalNet, 'description' => 'Net salaries payable'];

        return $this->journalEntryService->postModuleEntry([
            'branch_id' => $payrollRun->branch_id,
            'reference_number' => 'PAYROLL-'.$payrollRun->id.'-'.now()->format('YmdHis'),
            'entry_date' => $payrollRun->period_end,
            'description' => "Payroll run for {$payrollRun->period_start} to {$payrollRun->period_end}",
            'source_module' => 'payroll',
            'source_id' => $payrollRun->id,
            'lines' => $lines,
        ]);
    }

    protected function resolveAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."],
            ]);
        }

        return $account;
    }

    protected function calculateDeduction(StatutoryDeductionRule $rule, float $grossPay): float
    {
        return match ($rule->calculation_type) {
            'percentage' => round($grossPay * (float) ($rule->config['rate'] ?? 0), 2),
            'fixed' => round((float) ($rule->config['amount'] ?? 0), 2),
            'bracket' => $this->calculateBracketAmount($grossPay, $rule->config['brackets'] ?? []),
            default => 0.0,
        };
    }

    /**
     * Brackets are independent {min, max, rate} ranges (max nullable = open-ended),
     * not cumulative offsets, so each one is evaluated against its own [min, max).
     */
    protected function calculateBracketAmount(float $amount, array $brackets): float
    {
        $total = 0.0;

        foreach ($brackets as $bracket) {
            $min = (float) ($bracket['min'] ?? 0);
            $max = isset($bracket['max']) && $bracket['max'] !== null ? (float) $bracket['max'] : null;
            $rate = (float) ($bracket['rate'] ?? 0);

            if ($amount <= $min) {
                continue;
            }

            $ceiling = $max !== null ? min($amount, $max) : $amount;
            $taxableInBracket = $ceiling - $min;

            if ($taxableInBracket > 0) {
                $total += $taxableInBracket * $rate;
            }
        }

        return round($total, 2);
    }
}
