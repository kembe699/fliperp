import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchSuppliers } from '@/api/procurement'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Supplier } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { SupplierFormDialog } from '@/components/procurement/SupplierFormDialog'

export function SuppliersListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Supplier | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['suppliers', page],
    queryFn: () => fetchSuppliers({ page, per_page: 15 }),
  })

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
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchSuppliers({ per_page: 10000 })
                exportToCsv('suppliers.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('suppliers.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Supplier
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load suppliers. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No suppliers found"
          emptySubtext="Create a supplier to start recording purchases."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <SupplierFormDialog open={formOpen} onOpenChange={setFormOpen} supplier={editing} />
    </div>
  )
}
