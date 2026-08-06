import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchStockTransfers, fetchWarehouses } from '@/api/inventory'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { STOCK_TRANSFER_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { StockTransfer, StockTransferStatus } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StockTransferFormDialog } from '@/components/inventory/StockTransferFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: StockTransferStatus[] = ['pending', 'in_transit', 'completed', 'cancelled']

export function StockTransfersListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [fromWarehouseId, setFromWarehouseId] = useState('all')
  const [toWarehouseId, setToWarehouseId] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['stock-transfers', 'all'],
    queryFn: () => fetchStockTransfers({ per_page: 2000 }),
  })

  const warehouseName = (id: number) => warehouses?.find((w) => w.id === id)?.name ?? `#${id}`

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => fromWarehouseId === 'all' || row.from_warehouse_id === Number(fromWarehouseId))
      .filter((row) => toWarehouseId === 'all' || row.to_warehouse_id === Number(toWarehouseId))
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, fromWarehouseId, toWarehouseId, search])

  const columns: DataTableColumn<StockTransfer>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'from_warehouse_id', header: 'From', accessor: (row) => warehouseName(row.from_warehouse_id) },
    { key: 'to_warehouse_id', header: 'To', accessor: (row) => warehouseName(row.to_warehouse_id) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={STOCK_TRANSFER_STATUS_VARIANT[row.status]} /> },
    { key: 'created_at', header: 'Date', accessor: (row) => row.created_at, render: (row) => formatDate(row.created_at) },
  ]

  return (
    <div>
      <PageHeader
        parent="Inventory"
        title="Stock Transfers"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('stock-transfers.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('stock-transfers.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Transfer
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
                {s.replace('_', ' ')}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={fromWarehouseId} onValueChange={setFromWarehouseId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="From Warehouse" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Any from-warehouse</SelectItem>
            {warehouses?.map((w) => (
              <SelectItem key={w.id} value={String(w.id)}>
                {w.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={toWarehouseId} onValueChange={setToWarehouseId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="To Warehouse" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Any to-warehouse</SelectItem>
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
          options={[{ value: 'reference_number', label: 'Reference' }]}
          placeholder="Search stock transfers…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load stock transfers. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: StockTransfer) => navigate(`/stock-transfers/${row.id}`) }]}
          emptyTitle="No stock transfers found"
          emptySubtext="Create a transfer to move stock between warehouses."
        />
      )}

      <StockTransferFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
