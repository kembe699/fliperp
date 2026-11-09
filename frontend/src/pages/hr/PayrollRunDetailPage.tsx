import { useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Banknote, FileSpreadsheet, PlayCircle } from 'lucide-react'

import { downloadPayrollExcel, fetchPayrollRun, fetchPayslips, payAllPayslips, payPayslip, processPayrollRun } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { fetchPaymentTypes } from '@/api/pos'
import { fetchChartOfAccounts } from '@/api/reports'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Payslip, SalaryPaymentInput } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'

const NONE = '__none'
const today = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** Who is being paid: one employee, or everyone in the run still unpaid. */
type PayTarget = { kind: 'one'; payslip: Payslip } | { kind: 'all'; count: number; amount: number }

export function PayrollRunDetailPage() {
  const { id } = useParams<{ id: string }>()
  const payrollRunId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [payTarget, setPayTarget] = useState<PayTarget | null>(null)
  const [downloading, setDownloading] = useState(false)

  const { data: run, isLoading } = useQuery({ queryKey: ['payroll-run', payrollRunId], queryFn: () => fetchPayrollRun(payrollRunId), enabled: !!payrollRunId })
  const { data: payslips } = useQuery({ queryKey: ['payslips', payrollRunId], queryFn: () => fetchPayslips(payrollRunId), enabled: !!payrollRunId })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['payroll-run', payrollRunId] })
    queryClient.invalidateQueries({ queryKey: ['payslips', payrollRunId] })
    queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
  }

  const processMutation = useMutation({
    mutationFn: () => processPayrollRun(payrollRunId),
    onSuccess: () => {
      toast.success('Payroll run processed')
      refresh()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const summary = useMemo(() => {
    const list = payslips ?? []
    const paid = list.filter((p) => p.paid_at)
    const unpaid = list.filter((p) => !p.paid_at)
    const sum = (rows: Payslip[]) => rows.reduce((s, p) => s + Number(p.net_pay), 0)
    return { total: sum(list), paidCount: paid.length, paidAmount: sum(paid), unpaidCount: unpaid.length, unpaidAmount: sum(unpaid) }
  }, [payslips])

  if (isLoading || !run) {
    return <div className="p-6 text-sm text-muted-foreground">Loading payroll run…</div>
  }

  const canProcess = can('payroll-runs.process') && run.status === 'draft'
  const canPay = can('payroll-runs.process') && run.status !== 'draft'

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadPayrollExcel(payrollRunId, `payroll-${run.period_start.slice(0, 10)}-to-${run.period_end.slice(0, 10)}.xlsx`)
    } catch (error) {
      toast.error(getApiErrorInfo(error).message)
    } finally {
      setDownloading(false)
    }
  }

  const columns: DataTableColumn<Payslip>[] = [
    {
      key: 'employee_name',
      header: 'Employee',
      accessor: (row) => row.employee_name ?? `#${row.employee_id}`,
      render: (row) => (
        <div>
          <p className="font-medium text-foreground">{row.employee_name ?? `#${row.employee_id}`}</p>
          {row.employee_code && <p className="text-xs text-muted-foreground">{row.employee_code}</p>}
        </div>
      ),
    },
    { key: 'department', header: 'Department', accessor: (row) => row.department ?? '—' },
    { key: 'gross_pay', header: 'Gross Pay', className: 'text-right tabular-nums', accessor: (row) => Number(row.gross_pay), render: (row) => formatCurrency(row.gross_pay) },
    { key: 'total_deductions', header: 'Deductions', className: 'text-right tabular-nums', accessor: (row) => Number(row.total_deductions), render: (row) => formatCurrency(row.total_deductions) },
    { key: 'net_pay', header: 'Net Pay', className: 'text-right tabular-nums', accessor: (row) => Number(row.net_pay), render: (row) => <span className="font-semibold">{formatCurrency(row.net_pay)}</span> },
    {
      key: 'paid',
      header: 'Salary',
      accessor: (row) => (row.paid_at ? 'Paid' : 'Not paid'),
      filterable: true,
      render: (row) =>
        row.paid_at ? (
          <div>
            <StatusBadge label="Paid" variant="success" />
            <p className="mt-1 text-xs text-muted-foreground">
              {formatDate(row.paid_at)}
              {row.paid_by_name ? ` · ${row.paid_by_name}` : ''}
              {row.payment_type_name ? ` · ${row.payment_type_name}` : ''}
              {row.payment_reference ? ` · ${row.payment_reference}` : ''}
            </p>
          </div>
        ) : (
          <StatusBadge label="Not paid" variant="warning" />
        ),
    },
    {
      key: 'action',
      header: '',
      sortable: false,
      filterable: false,
      render: (row) =>
        canPay && !row.paid_at ? (
          <Button size="sm" className="gap-1.5" onClick={(e) => { e.stopPropagation(); setPayTarget({ kind: 'one', payslip: row }) }}>
            <Banknote className="h-3.5 w-3.5" /> Pay
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader
        parent="Payroll Runs"
        title={`${formatDate(run.period_start)} – ${formatDate(run.period_end)}`}
        action={
          <div className="flex flex-wrap gap-2">
            {run.status !== 'draft' && (
              <Button variant="outline" disabled={downloading} onClick={handleDownload}>
                <FileSpreadsheet className="h-4 w-4" />
                {downloading ? 'Preparing…' : 'Download Excel'}
              </Button>
            )}
            {canPay && summary.unpaidCount > 0 && (
              <Button onClick={() => setPayTarget({ kind: 'all', count: summary.unpaidCount, amount: summary.unpaidAmount })}>
                <Banknote className="h-4 w-4" />
                Pay all unpaid ({summary.unpaidCount})
              </Button>
            )}
            {canProcess && (
              <Button disabled={processMutation.isPending} onClick={() => processMutation.mutate()}>
                <PlayCircle className="h-4 w-4" />
                Process Payroll
              </Button>
            )}
          </div>
        }
      />

      <Card className="mb-6">
        <CardContent className="grid grid-cols-2 gap-6 p-6 sm:grid-cols-3 lg:grid-cols-6">
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Branch</p>
            <p className="mt-1 text-sm text-foreground">{run.branch_id ? branches?.find((b) => b.id === run.branch_id)?.name ?? `#${run.branch_id}` : 'All Branches'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
            <StatusBadge label={run.status} variant={run.status === 'paid' ? 'success' : run.status === 'processed' ? 'info' : 'neutral'} />
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Payslips</p>
            <p className="mt-1 text-sm text-foreground">{payslips?.length ?? 0}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Total Net Pay</p>
            <p className="mt-1 text-lg font-bold text-primary">{formatCurrency(summary.total)}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Paid</p>
            <p className="mt-1 text-lg font-bold text-success">{formatCurrency(summary.paidAmount)}</p>
            <p className="text-xs text-muted-foreground">{summary.paidCount} employee{summary.paidCount === 1 ? '' : 's'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Still to pay</p>
            <p className="mt-1 text-lg font-bold text-warning">{formatCurrency(summary.unpaidAmount)}</p>
            <p className="text-xs text-muted-foreground">{summary.unpaidCount} employee{summary.unpaidCount === 1 ? '' : 's'}</p>
          </div>
        </CardContent>
      </Card>

      {run.status === 'draft' ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
          This payroll run hasn't been processed yet. Click "Process Payroll" to generate payslips for all active employees with a salary structure and post the payroll journal entry.
        </p>
      ) : (
        <DataTable
          title="Payslips"
          columns={columns}
          data={payslips ?? []}
          rowKey={(row) => row.id}
          emptyTitle="No payslips"
          emptySubtext="No payslips were generated for this run."
          exportFileName={`payslips-${run.period_start.slice(0, 10)}`}
        />
      )}

      <PayDialog
        target={payTarget}
        onClose={() => setPayTarget(null)}
        onPay={async (values) => {
          if (!payTarget) return
          if (payTarget.kind === 'one') {
            await payPayslip(payrollRunId, payTarget.payslip.id, values)
            toast.success(`Salary paid to ${payTarget.payslip.employee_name ?? 'employee'}`)
          } else {
            const { paid } = await payAllPayslips(payrollRunId, values)
            toast.success(`${paid} salar${paid === 1 ? 'y' : 'ies'} paid`)
          }
          setPayTarget(null)
          refresh()
        }}
      />
    </div>
  )
}

function PayDialog({ target, onClose, onPay }: { target: PayTarget | null; onClose: () => void; onPay: (values: SalaryPaymentInput) => Promise<void> }) {
  const { data: paymentTypes = [] } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes, enabled: !!target })
  const { data: accounts = [] } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts, enabled: !!target })
  const cashAndBank = accounts.filter((a) => a.type === 'asset')
  const [paymentTypeId, setPaymentTypeId] = useState(NONE)
  const [accountId, setAccountId] = useState(NONE)
  const [reference, setReference] = useState('')
  const [paidAt, setPaidAt] = useState(today)
  const [saving, setSaving] = useState(false)

  const amount = target ? (target.kind === 'one' ? Number(target.payslip.net_pay) : target.amount) : 0
  const submit = async () => {
    setSaving(true)
    try {
      await onPay({
        payment_type_id: paymentTypeId === NONE ? undefined : Number(paymentTypeId),
        account_id: accountId === NONE ? undefined : Number(accountId),
        reference: reference.trim() || undefined,
        paid_at: paidAt,
      })
      setReference('')
    } catch (error) {
      toast.error(getApiErrorInfo(error).message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={!!target} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>
            {target?.kind === 'one' ? `Pay ${target.payslip.employee_name ?? 'employee'}` : `Pay ${target?.count ?? 0} unpaid employees`}
          </DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="rounded-lg bg-accent p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-primary">{target?.kind === 'one' ? 'Net pay' : 'Total net pay'}</p>
            <p className="text-2xl font-bold tabular-nums text-foreground">{formatCurrency(amount)}</p>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>Payment method</Label>
              <Select value={paymentTypeId} onValueChange={setPaymentTypeId}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NONE}>Not specified</SelectItem>
                  {paymentTypes.map((t) => (
                    <SelectItem key={t.id} value={String(t.id)}>
                      {t.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Date paid</Label>
              <Input type="date" value={paidAt} max={today()} onChange={(e) => setPaidAt(e.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Paid from</Label>
            <Select value={accountId} onValueChange={setAccountId}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>Cash (1000)</SelectItem>
                {cashAndBank
                  .filter((a) => a.code !== '1000')
                  .map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>
                      {a.code} {a.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Reference (optional)</Label>
            <Input value={reference} onChange={(e) => setReference(e.target.value)} placeholder="Bank transfer or mobile money reference" maxLength={255} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button disabled={saving} onClick={submit}>
            <Banknote className="h-4 w-4" />
            {saving ? 'Paying…' : target?.kind === 'one' ? 'Mark as paid' : 'Pay all'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
