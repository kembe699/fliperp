import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileBarChart, Pencil, Plus, Trash2 } from 'lucide-react'

import { createGrantDea, deleteGrant, deleteGrantBudgetLine, deleteGrantDea, fetchGrant, saveGrantBudgetLine, updateGrantDea } from '@/api/grants'
import { fetchChartOfAccounts } from '@/api/reports'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { GrantFormDialog } from '@/components/grants/GrantFormDialog'
import { SearchableSelect } from '@/components/shared/SearchableSelect'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function GrantDetailPage() {
  const { id } = useParams<{ id: string }>()
  const grantId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const canEdit = can('grants.update')

  const { data: grant, isLoading, isError } = useQuery({ queryKey: ['grant', grantId], queryFn: () => fetchGrant(grantId), enabled: !!grantId })
  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts, enabled: canEdit })

  const [editOpen, setEditOpen] = useState(false)
  const [deaCode, setDeaCode] = useState('')
  const [deaName, setDeaName] = useState('')
  const [lineAccountId, setLineAccountId] = useState<string | null>(null)
  const [lineDeaId, setLineDeaId] = useState<string | null>(null)
  const [lineAmount, setLineAmount] = useState('')

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['grant', grantId] })
    queryClient.invalidateQueries({ queryKey: ['dimensions'] })
  }
  const onError = (error: unknown) => toast.error(getApiErrorInfo(error).message)

  const addDea = useMutation({
    mutationFn: () => createGrantDea(grantId, { code: deaCode, name: deaName }),
    onSuccess: () => {
      toast.success('DEA added')
      setDeaCode('')
      setDeaName('')
      refresh()
    },
    onError,
  })
  const toggleDea = useMutation({
    mutationFn: (dea: { id: number; is_active: boolean }) => updateGrantDea(dea.id, { is_active: !dea.is_active }),
    onSuccess: refresh,
    onError,
  })
  const removeDea = useMutation({
    mutationFn: deleteGrantDea,
    onSuccess: () => {
      toast.success('DEA deleted')
      refresh()
    },
    onError,
  })
  const saveLine = useMutation({
    mutationFn: () => saveGrantBudgetLine(grantId, { account_id: Number(lineAccountId), dea_id: lineDeaId ? Number(lineDeaId) : null, budgeted_amount: Number(lineAmount) }),
    onSuccess: () => {
      toast.success('Budget line saved')
      setLineAccountId(null)
      setLineDeaId(null)
      setLineAmount('')
      refresh()
    },
    onError,
  })
  const removeLine = useMutation({
    mutationFn: deleteGrantBudgetLine,
    onSuccess: () => {
      toast.success('Budget line removed')
      refresh()
    },
    onError,
  })
  const removeGrant = useMutation({
    mutationFn: () => deleteGrant(grantId),
    onSuccess: () => {
      toast.success('SOF deleted')
      queryClient.invalidateQueries({ queryKey: ['grants'] })
      navigate('/grants')
    },
    onError,
  })

  if (isError) return <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this SOF.</p>
  if (isLoading || !grant) return <div className="p-6 text-sm text-muted-foreground">Loading SOF…</div>

  const deas = grant.deas ?? []
  const budgetLines = grant.budget_lines ?? []
  const budgeted = budgetLines.reduce((sum, line) => sum + line.budgeted_amount, 0)
  const money = (amount: number) => formatCurrency(amount, grant.currency_code)

  return (
    <div>
      <PageHeader
        parent="SOFs"
        title={`${grant.code} — ${grant.name}`}
        action={
          <div className="flex gap-2">
            {can('reports.view') && (
              <Button variant="outline" onClick={() => navigate(`/grants/${grant.id}/report`)}>
                <FileBarChart className="h-4 w-4" />
                Grant Report
              </Button>
            )}
            {canEdit && (
              <Button variant="outline" onClick={() => setEditOpen(true)}>
                <Pencil className="h-4 w-4" />
                Edit
              </Button>
            )}
            {can('grants.delete') && (
              <Button variant="outline" className="text-destructive" disabled={removeGrant.isPending} onClick={() => removeGrant.mutate()}>
                <Trash2 className="h-4 w-4" />
                Delete
              </Button>
            )}
          </div>
        }
      />

      <Card className="mb-6">
        <CardContent className="grid grid-cols-2 gap-6 p-6 sm:grid-cols-5">
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Donor</p>
            <p className="mt-1 text-sm text-foreground">{grant.donor_name ?? '—'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Period</p>
            <p className="mt-1 text-sm text-foreground">
              {formatDate(grant.start_date)} – {formatDate(grant.end_date)}
            </p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">SOF Currency</p>
            <p className="mt-1 text-sm text-foreground">{grant.currency_code}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Total Award</p>
            <p className="mt-1 text-sm font-medium text-foreground">{money(grant.budget_amount)}</p>
            {budgetLines.length > 0 && Math.abs(budgeted - grant.budget_amount) >= 0.01 && (
              <p className="text-xs text-warning">Budget lines total {money(budgeted)}</p>
            )}
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
            <StatusBadge label={grant.status === 'active' ? 'Active' : 'Closed'} variant={grant.status === 'active' ? 'success' : 'neutral'} />
          </div>
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div className="lg:col-span-2">
          <p className="mb-1 text-sm font-semibold text-foreground">DEAs (Detailed Expenditure Activities)</p>
          <p className="mb-3 text-xs text-muted-foreground">The budget lines within this SOF that transactions are coded to.</p>
          <Card>
            <CardContent className="p-4">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                    <th className="py-2">Code</th>
                    <th className="py-2">Name</th>
                    <th className="py-2">Status</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {deas.map((dea) => (
                    <tr key={dea.id} className="border-b border-border last:border-b-0">
                      <td className="py-2 font-medium text-foreground">{dea.code}</td>
                      <td className="py-2 text-foreground">{dea.name}</td>
                      <td className="py-2">
                        {canEdit ? (
                          <button type="button" className="text-xs underline" onClick={() => toggleDea.mutate(dea)}>
                            {dea.is_active ? 'Active' : 'Inactive'}
                          </button>
                        ) : (
                          <span className="text-xs">{dea.is_active ? 'Active' : 'Inactive'}</span>
                        )}
                      </td>
                      <td className="py-2 text-right">
                        {canEdit && (
                          <button type="button" aria-label={`Delete DEA ${dea.code}`} onClick={() => removeDea.mutate(dea.id)} className="text-muted-foreground hover:text-destructive">
                            <Trash2 className="h-4 w-4" />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {deas.length === 0 && (
                    <tr>
                      <td colSpan={4} className="py-4 text-center text-muted-foreground">
                        No DEAs yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
              {canEdit && (
                <div className="mt-4 flex items-end gap-2 border-t border-border pt-4">
                  <div className="w-28 space-y-1.5">
                    <Label required>Code</Label>
                    <Input value={deaCode} onChange={(event) => setDeaCode(event.target.value)} placeholder="DEA-01" />
                  </div>
                  <div className="flex-1 space-y-1.5">
                    <Label required>Name</Label>
                    <Input value={deaName} onChange={(event) => setDeaName(event.target.value)} placeholder="Teacher training" />
                  </div>
                  <Button disabled={!deaCode.trim() || !deaName.trim() || addDea.isPending} onClick={() => addDea.mutate()}>
                    <Plus className="h-4 w-4" />
                    Add
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="lg:col-span-3">
          <p className="mb-1 text-sm font-semibold text-foreground">Donor Budget ({grant.currency_code})</p>
          <p className="mb-3 text-xs text-muted-foreground">Per account, optionally per DEA. Saving an account and DEA again replaces its amount.</p>
          <Card>
            <CardContent className="p-4">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                    <th className="py-2">Account</th>
                    <th className="py-2">DEA</th>
                    <th className="py-2 text-right">Budget</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {budgetLines.map((line) => (
                    <tr key={line.id} className="border-b border-border last:border-b-0">
                      <td className="py-2 text-foreground">
                        {line.account_code} — {line.account_name}
                      </td>
                      <td className="py-2 text-muted-foreground">{line.dea_code ?? 'All'}</td>
                      <td className="py-2 text-right text-foreground">{money(line.budgeted_amount)}</td>
                      <td className="py-2 text-right">
                        {canEdit && (
                          <button type="button" aria-label="Remove budget line" onClick={() => removeLine.mutate(line.id)} className="text-muted-foreground hover:text-destructive">
                            <Trash2 className="h-4 w-4" />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {budgetLines.length === 0 && (
                    <tr>
                      <td colSpan={4} className="py-4 text-center text-muted-foreground">
                        No budget lines yet.
                      </td>
                    </tr>
                  )}
                </tbody>
                {budgetLines.length > 0 && (
                  <tfoot>
                    <tr className="border-t-2 border-border font-semibold text-foreground">
                      <td className="py-2" colSpan={2}>
                        Total
                      </td>
                      <td className="py-2 text-right">{money(budgeted)}</td>
                      <td />
                    </tr>
                  </tfoot>
                )}
              </table>
              {canEdit && (
                <div className="mt-4 grid grid-cols-1 items-end gap-2 border-t border-border pt-4 sm:grid-cols-[1fr_140px_130px_auto]">
                  <div className="space-y-1.5">
                    <Label required>Account</Label>
                    <SearchableSelect
                      options={(accounts ?? []).filter((account) => account.is_active).map((account) => ({ value: String(account.id), label: `${account.code} — ${account.name}` }))}
                      value={lineAccountId}
                      onChange={setLineAccountId}
                      placeholder="Select account"
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>DEA</Label>
                    <SearchableSelect
                      options={deas.map((dea) => ({ value: String(dea.id), label: dea.code, sublabel: dea.name }))}
                      value={lineDeaId}
                      onChange={setLineDeaId}
                      placeholder="All"
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label required>Amount ({grant.currency_code})</Label>
                    <Input type="number" min="0" step="0.01" value={lineAmount} onChange={(event) => setLineAmount(event.target.value)} />
                  </div>
                  <Button disabled={!lineAccountId || lineAmount === '' || saveLine.isPending} onClick={() => saveLine.mutate()}>
                    Save
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <GrantFormDialog open={editOpen} onOpenChange={setEditOpen} grant={grant} />
    </div>
  )
}
