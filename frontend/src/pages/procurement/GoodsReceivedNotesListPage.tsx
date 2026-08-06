import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchGoodsReceivedNotes, fetchSuppliers } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { GRN_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { GoodsReceivedNote, GrnStatus } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: GrnStatus[] = ['draft', 'confirmed']

export function GoodsReceivedNotesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['goods-received-notes', 'all'],
    queryFn: () => fetchGoodsReceivedNotes({ per_page: 2000 }),
  })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })

  const supplierName = (id: number) => suppliers?.data.find((s) => s.id === id)?.name ?? `#${id}`
  const warehouseName = (id: number) => warehouses?.find((w) => w.id === id)?.name ?? `#${id}`

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()) || supplierName(row.supplier_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search, suppliers])

  const columns: DataTableColumn<GoodsReceivedNote>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'supplier_id', header: 'Supplier', accessor: (row) => supplierName(row.supplier_id) },
    { key: 'warehouse_id', header: 'Warehouse', accessor: (row) => warehouseName(row.warehouse_id) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={GRN_STATUS_VARIANT[row.status]} /> },
    { key: 'received_date', header: 'Received Date', accessor: (row) => row.received_date, render: (row) => formatDate(row.received_date) },
  ]

  return (
    <div>
      <PageHeader
        parent="Procurement"
        title="Goods Received Notes"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('goods-received-notes.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('goods-received-notes.create') && (
              <Button onClick={() => navigate('/goods-received-notes/new')}>
                <Plus className="h-4 w-4" />
                New GRN
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
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {s}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'reference_number', label: 'Reference' },
            { value: 'supplier', label: 'Supplier' },
          ]}
          placeholder="Search goods received notes…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load goods received notes. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: GoodsReceivedNote) => navigate(`/goods-received-notes/${row.id}`) }]}
          emptyTitle="No goods received notes found"
          emptySubtext="GRNs record stock received from suppliers, optionally against a purchase order."
        />
      )}
    </div>
  )
}
