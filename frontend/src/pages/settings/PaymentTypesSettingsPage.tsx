import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createPaymentType, deletePaymentType, updatePaymentType } from '@/api/settings'
import { fetchPaymentTypes } from '@/api/pos'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { PaymentType, PaymentTypeKind } from '@/types/pos'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const KINDS: PaymentTypeKind[] = ['cash', 'card', 'mobile_money', 'bank_transfer', 'credit']
const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function PaymentTypesSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: paymentTypes, isLoading, isError } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes })

  const [kindFilter, setKindFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<PaymentType | null>(null)
  const [name, setName] = useState('')
  const [kind, setKind] = useState<PaymentTypeKind>('cash')

  const filtered = useMemo(() => {
    return (paymentTypes ?? [])
      .filter((row) => kindFilter === 'all' || row.type === kindFilter)
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [paymentTypes, kindFilter, search])

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setKind(editing?.type ?? 'cash')
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, type: kind }
      return editing ? updatePaymentType(editing.id, payload) : createPaymentType(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Payment type updated' : 'Payment type created')
      queryClient.invalidateQueries({ queryKey: ['payment-types'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deletePaymentType,
    onSuccess: () => {
      toast.success('Payment type deleted')
      queryClient.invalidateQueries({ queryKey: ['payment-types'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<PaymentType>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'type', header: 'Kind', accessor: (row) => row.type.replace('_', ' ') },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: PaymentType) => DataTableRowAction<PaymentType>[] = (row) => [
    ...(can('payment-types.update') ? [{ label: 'Edit', onClick: (p: PaymentType) => { setEditing(p); setFormOpen(true) } }] : []),
    ...(can('payment-types.delete') ? [{ label: 'Delete', destructive: true, onClick: (p: PaymentType) => deleteMutation.mutate(p.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Payment Types"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('payment-types.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('payment-types.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Payment Type
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={kindFilter} onValueChange={setKindFilter}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Kind" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All kinds</SelectItem>
            {KINDS.map((k) => (
              <SelectItem key={k} value={k}>
                {k.replace('_', ' ')}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search payment types…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load payment types. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No payment types found"
          emptySubtext="Create a payment type to accept payments in POS and invoices."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Payment Type' : 'New Payment Type'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Kind</Label>
              <Select value={kind} onValueChange={(value) => setKind(value as PaymentTypeKind)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {KINDS.map((k) => (
                    <SelectItem key={k} value={k}>
                      {k.replace('_', ' ')}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Payment Type'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
