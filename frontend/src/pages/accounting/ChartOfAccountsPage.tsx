import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createChartOfAccount, deleteChartOfAccount, updateChartOfAccount } from '@/api/accounting'
import { fetchChartOfAccounts } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { ChartOfAccount, ChartOfAccountType } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const TYPES: ChartOfAccountType[] = ['asset', 'liability', 'equity', 'revenue', 'expense']
const TYPE_VARIANT: Record<ChartOfAccountType, 'info' | 'warning' | 'neutral' | 'success' | 'danger'> = {
  asset: 'info',
  liability: 'warning',
  equity: 'neutral',
  revenue: 'success',
  expense: 'danger',
}

export function ChartOfAccountsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: accounts, isLoading, isError } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<ChartOfAccount | null>(null)
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [type, setType] = useState<ChartOfAccountType>('asset')
  const [parentId, setParentId] = useState('none')
  const [isActive, setIsActive] = useState(true)

  useEffect(() => {
    if (!formOpen) return
    setCode(editing?.code ?? '')
    setName(editing?.name ?? '')
    setType(editing?.type ?? 'asset')
    setParentId(editing?.parent_id ? String(editing.parent_id) : 'none')
    setIsActive(editing?.is_active ?? true)
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { code, name, type, parent_id: parentId === 'none' ? null : Number(parentId), is_active: isActive }
      return editing ? updateChartOfAccount(editing.id, payload) : createChartOfAccount(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Account updated' : 'Account created')
      queryClient.invalidateQueries({ queryKey: ['chart-of-accounts'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteChartOfAccount,
    onSuccess: () => {
      toast.success('Account deleted')
      queryClient.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<ChartOfAccount>[] = [
    { key: 'code', header: 'Code', accessor: (row) => row.code, sortable: true },
    { key: 'name', header: 'Name', accessor: (row) => row.name },
    { key: 'parent_id', header: 'Parent', render: (row) => (row.parent_id ? accounts?.find((a) => a.id === row.parent_id)?.name ?? `#${row.parent_id}` : '—') },
    { key: 'type', header: 'Type', render: (row) => <StatusBadge label={row.type} variant={TYPE_VARIANT[row.type]} /> },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: ChartOfAccount) => DataTableRowAction<ChartOfAccount>[] = (row) => [
    ...(can('chart-of-accounts.update') ? [{ label: 'Edit', onClick: (a: ChartOfAccount) => { setEditing(a); setFormOpen(true) } }] : []),
    ...(can('chart-of-accounts.delete') ? [{ label: 'Delete', destructive: true, onClick: (a: ChartOfAccount) => deleteMutation.mutate(a.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Accounting"
        title="Chart of Accounts"
        action={
          can('chart-of-accounts.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Account
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load chart of accounts. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={[...(accounts ?? [])].sort((a, b) => a.code.localeCompare(b.code))}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No accounts found"
          emptySubtext="Create your first chart of accounts entry to start recording journal entries."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Account' : 'New Account'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Code</Label>
                <Input value={code} onChange={(event) => setCode(event.target.value)} placeholder="1000" />
              </div>
              <div className="space-y-1.5">
                <Label>Name</Label>
                <Input value={name} onChange={(event) => setName(event.target.value)} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Type</Label>
                <Select value={type} onValueChange={(value) => setType(value as ChartOfAccountType)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {TYPES.map((t) => (
                      <SelectItem key={t} value={t}>
                        {t}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Parent Account</Label>
                <Select value={parentId} onValueChange={setParentId}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None (top-level)</SelectItem>
                    {accounts?.filter((a) => a.id !== editing?.id).map((account) => (
                      <SelectItem key={account.id} value={String(account.id)}>
                        {account.code} — {account.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" checked={isActive} onChange={(event) => setIsActive(event.target.checked)} className="h-4 w-4 rounded border-input" />
              Active
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!code || !name || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Account'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
