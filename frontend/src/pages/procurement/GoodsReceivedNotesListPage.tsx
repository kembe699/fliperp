import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchGoodsReceivedNotes, fetchSuppliers } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { GRN_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { GoodsReceivedNote } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'

export function GoodsReceivedNotesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [page, setPage] = useState(1)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['goods-received-notes', page],
    queryFn: () => fetchGoodsReceivedNotes({ page, per_page: 15 }),
  })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })

  const supplierName = (id: number) => suppliers?.data.find((s) => s.id === id)?.name ?? `#${id}`
  const warehouseName = (id: number) => warehouses?.find((w) => w.id === id)?.name ?? `#${id}`

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
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchGoodsReceivedNotes({ per_page: 10000 })
                exportToCsv('goods-received-notes.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('goods-received-notes.create') && (
              <Button onClick={() => navigate('/goods-received-notes/new')}>
                <Plus className="h-4 w-4" />
                New GRN
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load goods received notes. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: GoodsReceivedNote) => navigate(`/goods-received-notes/${row.id}`) }]}
          emptyTitle="No goods received notes found"
          emptySubtext="GRNs record stock received from suppliers, optionally against a purchase order."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
