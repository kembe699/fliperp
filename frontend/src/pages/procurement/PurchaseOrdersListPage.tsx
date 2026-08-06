import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchPurchaseOrders, fetchSuppliers } from '@/api/procurement'
import { fetchBranches } from '@/api/branches'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { PURCHASE_ORDER_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { PurchaseOrder, PurchaseOrderStatus } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: PurchaseOrderStatus[] = ['draft', 'submitted', 'approved', 'partially_received', 'received', 'cancelled']

export function PurchaseOrdersListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [supplierId, setSupplierId] = useState('all')
  const [branchId, setBranchId] = useState('all')
  const [search, setSearch] = useState('')

  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['purchase-orders', 'all'],
    queryFn: () => fetchPurchaseOrders({ per_page: 2000 }),
  })

  const supplierName = (id: number) => suppliers?.data.find((s) => s.id === id)?.name ?? `#${id}`
  const total = (po: PurchaseOrder) => po.items.reduce((sum, item) => sum + Number(item.quantity_ordered) * Number(item.unit_cost), 0)

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => supplierId === 'all' || row.supplier_id === Number(supplierId))
      .filter((row) => branchId === 'all' || row.branch_id === Number(branchId))
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()) || supplierName(row.supplier_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, status, supplierId, branchId, search, suppliers])

  const columns: DataTableColumn<PurchaseOrder>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'supplier_id', header: 'Supplier', render: (row) => supplierName(row.supplier_id) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace(/_/g, ' ')} variant={PURCHASE_ORDER_STATUS_VARIANT[row.status]} /> },
    { key: 'total', header: 'Total', accessor: (row) => total(row), render: (row) => formatCurrency(total(row)) },
    { key: 'order_date', header: 'Order Date', accessor: (row) => row.order_date, render: (row) => formatDate(row.order_date) },
  ]

  return (
    <div>
      <PageHeader
        parent="Procurement"
        title="Purchase Orders"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('purchase-orders.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('purchase-orders.create') && (
              <Button onClick={() => navigate('/purchase-orders/new')}>
                <Plus className="h-4 w-4" />
                New Purchase Order
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
                {s.replace(/_/g, ' ')}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={supplierId} onValueChange={setSupplierId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Supplier" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All suppliers</SelectItem>
            {suppliers?.data.map((s) => (
              <SelectItem key={s.id} value={String(s.id)}>
                {s.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={branchId} onValueChange={setBranchId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Branch" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All branches</SelectItem>
            {branches?.map((b) => (
              <SelectItem key={b.id} value={String(b.id)}>
                {b.name}
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
          placeholder="Search purchase orders…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load purchase orders. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: PurchaseOrder) => navigate(`/purchase-orders/${row.id}`) }]}
          emptyTitle="No purchase orders found"
          emptySubtext="Create a purchase order to start procuring stock."
        />
      )}
    </div>
  )
}
