import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createTaxRate, deleteTaxRate, updateTaxRate } from '@/api/settings'
import { fetchTaxRates } from '@/api/pos'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { TaxRate } from '@/types/pos'

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

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function TaxRatesSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: taxRates, isLoading, isError } = useQuery({ queryKey: ['tax-rates'], queryFn: fetchTaxRates })

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<TaxRate | null>(null)
  const [name, setName] = useState('')
  const [rate, setRate] = useState('')
  const [isDefault, setIsDefault] = useState(false)

  const filtered = useMemo(() => {
    return (taxRates ?? [])
      .filter((row) => status === 'all' || (status === 'active' ? row.is_active : !row.is_active))
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [taxRates, status, search])

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setRate(editing ? String(editing.rate) : '')
    setIsDefault(editing?.is_default ?? false)
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, rate: Number(rate), is_default: isDefault }
      return editing ? updateTaxRate(editing.id, payload) : createTaxRate(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Tax rate updated' : 'Tax rate created')
      queryClient.invalidateQueries({ queryKey: ['tax-rates'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteTaxRate,
    onSuccess: () => {
      toast.success('Tax rate deleted')
      queryClient.invalidateQueries({ queryKey: ['tax-rates'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<TaxRate>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'rate', header: 'Rate', accessor: (row) => row.rate, render: (row) => `${row.rate}%` },
    { key: 'is_default', header: 'Default', render: (row) => (row.is_default ? <StatusBadge label="Default" variant="info" /> : '—') },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: TaxRate) => DataTableRowAction<TaxRate>[] = (row) => [
    ...(can('tax-rates.update') ? [{ label: 'Edit', onClick: (t: TaxRate) => { setEditing(t); setFormOpen(true) } }] : []),
    ...(can('tax-rates.delete') ? [{ label: 'Delete', destructive: true, onClick: (t: TaxRate) => deleteMutation.mutate(t.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Tax Rates"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('tax-rates.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('tax-rates.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Tax Rate
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search tax rates…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load tax rates. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No tax rates found"
          emptySubtext="Create a tax rate to apply to products and invoices."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Tax Rate' : 'New Tax Rate'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Name</Label>
                <Input value={name} onChange={(event) => setName(event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>Rate (%)</Label>
                <Input type="number" min="0" max="100" step="0.01" value={rate} onChange={(event) => setRate(event.target.value)} />
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" checked={isDefault} onChange={(event) => setIsDefault(event.target.checked)} className="h-4 w-4 rounded border-input" />
              Default tax rate
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || !rate || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Tax Rate'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
