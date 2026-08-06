import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchSuppliers } from '@/api/procurement'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Supplier } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SupplierFormDialog } from '@/components/procurement/SupplierFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function SuppliersListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Supplier | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['suppliers', 'all'],
    queryFn: () => fetchSuppliers({ per_page: 2000 }),
  })

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || (status === 'active' ? row.is_active : !row.is_active))
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

  const columns: DataTableColumn<Supplier>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'contact_person', header: 'Contact Person', accessor: (row) => row.contact_person ?? '—' },
    { key: 'phone', header: 'Phone', accessor: (row) => row.phone ?? '—' },
    { key: 'payment_terms_days', header: 'Payment Terms', accessor: (row) => `${row.payment_terms_days} days` },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: Supplier) => DataTableRowAction<Supplier>[] = (row) => [
    ...(can('suppliers.update') ? [{ label: 'Edit', onClick: (s: Supplier) => { setEditing(s); setFormOpen(true) } }] : []),
    { label: 'View Statement', onClick: (s: Supplier) => navigate(`/suppliers/${s.id}/statement`) },
  ]

  return (
    <div>
      <PageHeader
        parent="Procurement"
        title="Suppliers"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('suppliers.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('suppliers.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Supplier
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
          placeholder="Search suppliers…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load suppliers. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No suppliers found"
          emptySubtext="Create a supplier to start recording purchases."
        />
      )}

      <SupplierFormDialog open={formOpen} onOpenChange={setFormOpen} supplier={editing} />
    </div>
  )
}
