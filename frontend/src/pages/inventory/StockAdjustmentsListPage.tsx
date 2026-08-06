import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchStockAdjustments, fetchWarehouses } from '@/api/inventory'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { STOCK_ADJUSTMENT_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { StockAdjustment, StockAdjustmentStatus } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StockAdjustmentFormDialog } from '@/components/inventory/StockAdjustmentFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: StockAdjustmentStatus[] = ['draft', 'approved']

export function StockAdjustmentsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [warehouseId, setWarehouseId] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['stock-adjustments', 'all'],
    queryFn: () => fetchStockAdjustments({ per_page: 2000 }),
  })

  const warehouseName = (id: number) => warehouses?.find((w) => w.id === id)?.name ?? `#${id}`

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => warehouseId === 'all' || row.warehouse_id === Number(warehouseId))
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()) || (row.reason ?? '').toLowerCase().includes(search.toLowerCase()))
  }, [data, status, warehouseId, search])

  const columns: DataTableColumn<StockAdjustment>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'warehouse_id', header: 'Warehouse', accessor: (row) => warehouseName(row.warehouse_id) },
    { key: 'reason', header: 'Reason', accessor: (row) => row.reason ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STOCK_ADJUSTMENT_STATUS_VARIANT[row.status]} /> },
    { key: 'created_at', header: 'Date', accessor: (row) => row.created_at, render: (row) => formatDate(row.created_at) },
  ]

  return (
    <div>
      <PageHeader
        parent="Inventory"
        title="Stock Adjustments"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('stock-adjustments.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('stock-adjustments.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Adjustment
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

        <Select value={warehouseId} onValueChange={setWarehouseId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Warehouse" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All warehouses</SelectItem>
            {warehouses?.map((w) => (
              <SelectItem key={w.id} value={String(w.id)}>
                {w.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'reference_number', label: 'Reference' },
            { value: 'reason', label: 'Reason' },
          ]}
          placeholder="Search stock adjustments…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load stock adjustments. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: StockAdjustment) => navigate(`/stock-adjustments/${row.id}`) }]}
          emptyTitle="No stock adjustments found"
          emptySubtext="Create an adjustment to reconcile counted stock against system quantities."
        />
      )}

      <StockAdjustmentFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
