<?php

namespace App\Exports;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One payroll run as a spreadsheet: a row per employee, each deduction its own column, who was paid. */
class PayrollRunExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /** @var array<int, string> */
    protected array $deductionNames;

    public function __construct(protected PayrollRun $run)
    {
        $this->run->loadMissing('payslips.employee.department', 'payslips.paidBy', 'payslips.paymentType');
        $this->deductionNames = $this->run->payslips
            ->flatMap(fn ($p) => array_keys($p->breakdown['deductions'] ?? []))
            ->unique()->values()->all();
    }

    public function title(): string
    {
        return 'Payroll '.substr((string) $this->run->period_start, 0, 7);
    }

    public function headings(): array
    {
        return array_merge(
            ['Employee code', 'Employee', 'Department', 'Basic salary', 'Allowances', 'Gross pay'],
            $this->deductionNames,
            ['Total deductions', 'Net pay', 'Status', 'Paid on', 'Paid by', 'Method', 'Reference'],
        );
    }

    public function array(): array
    {
        $rows = [];
        $totals = ['basic' => 0.0, 'allowances' => 0.0, 'gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0, 'by' => array_fill_keys($this->deductionNames, 0.0)];

        foreach ($this->run->payslips->sortBy(fn ($p) => $p->employee?->first_name) as $p) {
            $b = $p->breakdown ?? [];
            $allowances = (float) collect($b['allowances'] ?? [])->sum();
            $row = [
                $p->employee?->employee_code,
                trim(($p->employee?->first_name ?? '').' '.($p->employee?->last_name ?? '')),
                $p->employee?->department?->name,
                (float) ($b['basic_salary'] ?? 0),
                $allowances,
                (float) $p->gross_pay,
            ];
            foreach ($this->deductionNames as $name) {
                $amount = (float) ($b['deductions'][$name] ?? 0);
                $row[] = $amount;
                $totals['by'][$name] += $amount;
            }
            $row = array_merge($row, [
                (float) $p->total_deductions,
                (float) $p->net_pay,
                $p->paid_at ? 'Paid' : 'Not paid',
                $p->paid_at?->format('Y-m-d H:i'),
                $p->paidBy?->name,
                $p->paymentType?->name,
                $p->payment_reference,
            ]);
            $rows[] = $row;

            $totals['basic'] += (float) ($b['basic_salary'] ?? 0);
            $totals['allowances'] += $allowances;
            $totals['gross'] += (float) $p->gross_pay;
            $totals['deductions'] += (float) $p->total_deductions;
            $totals['net'] += (float) $p->net_pay;
        }

        $paid = $this->run->payslips->whereNotNull('paid_at')->count();
        $rows[] = array_merge(
            ['', 'TOTAL', '', round($totals['basic'], 2), round($totals['allowances'], 2), round($totals['gross'], 2)],
            array_map(fn ($v) => round($v, 2), array_values($totals['by'])),
            [round($totals['deductions'], 2), round($totals['net'], 2), "{$paid} of {$this->run->payslips->count()} paid", '', '', '', ''],
        );

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $last = $this->run->payslips->count() + 2;

        return [1 => ['font' => ['bold' => true]], $last => ['font' => ['bold' => true]]];
    }
}
