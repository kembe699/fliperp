import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { PlayCircle } from 'lucide-react'

import { fetchEmployees, fetchPayrollRun, fetchPayslips, processPayrollRun } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'

export function PayrollRunDetailPage() {
  const { id } = useParams<{ id: string }>()
  const payrollRunId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: run, isLoading } = useQuery({ queryKey: ['payroll-run', payrollRunId], queryFn: () => fetchPayrollRun(payrollRunId), enabled: !!payrollRunId })
  const { data: payslips } = useQuery({ queryKey: ['payslips', payrollRunId], queryFn: () => fetchPayslips(payrollRunId), enabled: !!payrollRunId })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })

  const employeeName = (eid: number) => {
    const employee = employees?.data.find((e) => e.id === eid)
    return employee ? `${employee.first_name} ${employee.last_name}` : `#${eid}`
  }

  const processMutation = useMutation({
    mutationFn: () => processPayrollRun(payrollRunId),
    onSuccess: () => {
      toast.success('Payroll run processed')
      queryClient.invalidateQueries({ queryKey: ['payroll-run', payrollRunId] })
      queryClient.invalidateQueries({ queryKey: ['payslips', payrollRunId] })
      queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !run) {
    return <div className="p-6 text-sm text-muted-foreground">Loading payroll run…</div>
  }

  const canProcess = can('payroll-runs.process') && run.status === 'draft'
  const totalNet = (payslips ?? []).reduce((sum, p) => sum + Number(p.net_pay), 0)

  const columns: DataTableColumn<NonNullable<typeof payslips>[number]>[] = [
    { key: 'employee_id', header: 'Employee', render: (row) => employeeName(row.employee_id) },
    { key: 'gross_pay', header: 'Gross Pay', render: (row) => formatCurrency(row.gross_pay) },
    { key: 'total_deductions', header: 'Deductions', render: (row) => formatCurrency(row.total_deductions) },
    { key: 'net_pay', header: 'Net Pay', render: (row) => <span className="font-medium">{formatCurrency(row.net_pay)}</span> },
  ]

  return (
    <div>
      <PageHeader
        parent="Payroll Runs"
        title={`${formatDate(run.period_start)} – ${formatDate(run.period_end)}`}
        action={
          canProcess && (
            <Button disabled={processMutation.isPending} onClick={() => processMutation.mutate()}>
              <PlayCircle className="h-4 w-4" />
              Process Payroll
            </Button>
          )
        }
      />

      <Card className="mb-6">
        <CardContent className="grid grid-cols-2 gap-6 p-6 sm:grid-cols-4">
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Branch</p>
            <p className="mt-1 text-sm text-foreground">{run.branch_id ? branches?.find((b) => b.id === run.branch_id)?.name ?? `#${run.branch_id}` : 'All Branches'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
            <StatusBadge label={run.status} variant={run.status === 'processed' ? 'success' : 'neutral'} />
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Payslips</p>
            <p className="mt-1 text-sm text-foreground">{payslips?.length ?? 0}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Total Net Pay</p>
            <p className="mt-1 text-lg font-bold text-primary">{formatCurrency(totalNet)}</p>
          </div>
        </CardContent>
      </Card>

      {run.status === 'draft' ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
          This payroll run hasn't been processed yet. Click "Process Payroll" to generate payslips for all active employees with a salary structure and post the payroll journal entry.
        </p>
      ) : (
        <DataTable columns={columns} data={payslips ?? []} rowKey={(row) => row.id} emptyTitle="No payslips" emptySubtext="No payslips were generated for this run." />
      )}
    </div>
  )
}
