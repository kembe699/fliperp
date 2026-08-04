import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createBudgetLine, deleteBudgetLine, fetchBudgetLines, fetchBudgetPeriod, fetchBudgetVsActual, updateBudgetPeriod } from '@/api/budgeting'
import { fetchChartOfAccounts } from '@/api/reports'
import { fetchBranches } from '@/api/branches'
import { fetchDepartments } from '@/api/hr'
import { formatCurrency, formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

export function BudgetPeriodDetailPage() {
  const { id } = useParams<{ id: string }>()
  const budgetPeriodId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [lineOpen, setLineOpen] = useState(false)
  const [accountId, setAccountId] = useState<string | null>(null)
  const [branchId, setBranchId] = useState('none')
  const [departmentId, setDepartmentId] = useState('none')
  const [budgetedAmount, setBudgetedAmount] = useState('')

  const { data: period, isLoading } = useQuery({ queryKey: ['budget-period', budgetPeriodId], queryFn: () => fetchBudgetPeriod(budgetPeriodId), enabled: !!budgetPeriodId })
  const { data: lines } = useQuery({ queryKey: ['budget-lines', budgetPeriodId], queryFn: () => fetchBudgetLines(budgetPeriodId), enabled: !!budgetPeriodId })
  const { data: vsActual } = useQuery({ queryKey: ['budget-vs-actual', budgetPeriodId], queryFn: () => fetchBudgetVsActual(budgetPeriodId), enabled: !!budgetPeriodId })
  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts, enabled: lineOpen })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments })

  useEffect(() => {
    if (lineOpen) {
      setAccountId(null)
      setBranchId('none')
      setDepartmentId('none')
      setBudgetedAmount('')
    }
  }, [lineOpen])

  const lineMutation = useMutation({
    mutationFn: () =>
      createBudgetLine({
        budget_period_id: budgetPeriodId,
        account_id: Number(accountId),
        branch_id: branchId === 'none' ? null : Number(branchId),
        department_id: departmentId === 'none' ? null : Number(departmentId),
        budgeted_amount: Number(budgetedAmount),
      }),
    onSuccess: () => {
      toast.success('Budget line added')
      queryClient.invalidateQueries({ queryKey: ['budget-lines', budgetPeriodId] })
      queryClient.invalidateQueries({ queryKey: ['budget-vs-actual', budgetPeriodId] })
      setLineOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteLineMutation = useMutation({
    mutationFn: deleteBudgetLine,
    onSuccess: () => {
      toast.success('Budget line removed')
      queryClient.invalidateQueries({ queryKey: ['budget-lines', budgetPeriodId] })
      queryClient.invalidateQueries({ queryKey: ['budget-vs-actual', budgetPeriodId] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const statusMutation = useMutation({
    mutationFn: (status: 'active' | 'closed') => updateBudgetPeriod(budgetPeriodId, { status }),
    onSuccess: (_data, status) => {
      toast.success(status === 'active' ? 'Budget period activated' : 'Budget period closed')
      queryClient.invalidateQueries({ queryKey: ['budget-period', budgetPeriodId] })
      queryClient.invalidateQueries({ queryKey: ['budget-periods'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !period) {
    return <div className="p-6 text-sm text-muted-foreground">Loading budget period…</div>
  }

  const totalBudgeted = vsActual?.reduce((sum, l) => sum + l.budgeted_amount, 0) ?? 0
  const totalActual = vsActual?.reduce((sum, l) => sum + l.actual_amount, 0) ?? 0

  return (
    <div>
      <PageHeader
        parent="Budget Periods"
        title={period.name}
        action={
          <div className="flex gap-2">
            {can('budget-periods.update') && period.status === 'draft' && (
              <Button variant="outline" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate('active')}>
                Activate
              </Button>
            )}
            {can('budget-periods.update') && period.status === 'active' && (
              <Button variant="outline" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate('closed')}>
                Close Period
              </Button>
            )}
            {can('budget-lines.create') && (
              <Button onClick={() => setLineOpen(true)}>
                <Plus className="h-4 w-4" />
                Add Budget Line
              </Button>
            )}
          </div>
        }
      />

      <Card className="mb-6">
        <CardContent className="grid grid-cols-2 gap-6 p-6 sm:grid-cols-4">
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Period</p>
            <p className="mt-1 text-sm text-foreground">{formatDate(period.start_date)} – {formatDate(period.end_date)}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
            <StatusBadge label={period.status} variant={period.status === 'active' ? 'success' : period.status === 'closed' ? 'info' : 'neutral'} />
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Total Budgeted</p>
            <p className="mt-1 text-sm font-medium text-foreground">{formatCurrency(totalBudgeted)}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Total Actual</p>
            <p className="mt-1 text-lg font-bold text-primary">{formatCurrency(totalActual)}</p>
          </div>
        </CardContent>
      </Card>

      <p className="mb-3 text-sm font-semibold text-foreground">Budget vs Actual</p>
      <Card className="mb-6">
        <CardContent className="p-6">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Account</th>
                <th className="py-2 text-right">Budgeted</th>
                <th className="py-2 text-right">Actual</th>
                <th className="py-2 text-right">Variance</th>
                <th className="py-2 text-right">Utilization</th>
              </tr>
            </thead>
            <tbody>
              {vsActual?.map((line) => {
                const overBudget = line.variance < 0
                return (
                  <tr key={line.budget_line_id} className="border-b border-border last:border-b-0">
                    <td className="py-2 text-foreground">
                      {line.account_code} — {line.account_name}
                    </td>
                    <td className="py-2 text-right text-foreground">{formatCurrency(line.budgeted_amount)}</td>
                    <td className="py-2 text-right text-foreground">{formatCurrency(line.actual_amount)}</td>
                    <td className={`py-2 text-right font-medium ${overBudget ? 'text-danger' : 'text-success'}`}>
                      {overBudget ? '' : '+'}
                      {formatCurrency(line.variance)}
                    </td>
                    <td className="py-2 text-right">
                      {line.utilization_percent !== null ? (
                        <span className={line.utilization_percent > 100 ? 'font-medium text-danger' : 'text-foreground'}>{line.utilization_percent}%</span>
                      ) : (
                        '—'
                      )}
                    </td>
                  </tr>
                )
              })}
              {(!vsActual || vsActual.length === 0) && (
                <tr>
                  <td colSpan={5} className="py-6 text-center text-muted-foreground">
                    No budget lines to compare yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      <p className="mb-3 text-sm font-semibold text-foreground">Budget Lines</p>
      <Card>
        <CardContent className="p-6">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Account</th>
                <th className="py-2">Branch</th>
                <th className="py-2">Department</th>
                <th className="py-2 text-right">Budgeted Amount</th>
                <th className="py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {lines?.map((line) => (
                <tr key={line.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">{accounts?.find((a) => a.id === line.account_id)?.name ?? `#${line.account_id}`}</td>
                  <td className="py-2 text-muted-foreground">{line.branch_id ? branches?.find((b) => b.id === line.branch_id)?.name ?? `#${line.branch_id}` : 'All'}</td>
                  <td className="py-2 text-muted-foreground">{line.department_id ? departments?.find((d) => d.id === line.department_id)?.name ?? `#${line.department_id}` : 'All'}</td>
                  <td className="py-2 text-right text-foreground">{formatCurrency(line.budgeted_amount)}</td>
                  <td className="py-2 text-right">
                    {can('budget-lines.delete') && (
                      <button type="button" onClick={() => deleteLineMutation.mutate(line.id)} className="text-muted-foreground hover:text-destructive">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {(!lines || lines.length === 0) && (
                <tr>
                  <td colSpan={5} className="py-6 text-center text-muted-foreground">
                    No budget lines added yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      <Dialog open={lineOpen} onOpenChange={setLineOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Budget Line</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Account</Label>
              <SearchableSelect
                options={(accounts ?? []).map((account) => ({ value: String(account.id), label: `${account.code} — ${account.name}` }))}
                value={accountId}
                onChange={setAccountId}
                placeholder="Select account"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Branch (optional)</Label>
                <Select value={branchId} onValueChange={setBranchId}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">All branches</SelectItem>
                    {branches?.map((branch) => (
                      <SelectItem key={branch.id} value={String(branch.id)}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Department (optional)</Label>
                <Select value={departmentId} onValueChange={setDepartmentId}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">All departments</SelectItem>
                    {departments?.map((department) => (
                      <SelectItem key={department.id} value={String(department.id)}>
                        {department.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>Budgeted Amount</Label>
              <Input type="number" min="0" step="0.01" value={budgetedAmount} onChange={(event) => setBudgetedAmount(event.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setLineOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!accountId || !budgetedAmount || lineMutation.isPending} onClick={() => lineMutation.mutate()}>
              Add Line
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
