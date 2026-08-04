import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchDispatches, fetchVehicles } from '@/api/logistics'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { Dispatch, DispatchStatus } from '@/types/logistics'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { DispatchFormDialog } from '@/components/logistics/DispatchFormDialog'

const STATUS_VARIANT: Record<DispatchStatus, 'warning' | 'info' | 'success' | 'danger'> = {
  pending: 'warning',
  in_transit: 'info',
  delivered: 'success',
  cancelled: 'danger',
}

export function DispatchesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['dispatches', page], queryFn: () => fetchDispatches({ page, per_page: 15 }) })
  const { data: vehicles } = useQuery({ queryKey: ['vehicles'], queryFn: fetchVehicles })

  const vehicleLabel = (id: number | null) => (id ? vehicles?.find((v) => v.id === id)?.registration_number ?? `#${id}` : 'Unassigned')

  const columns: DataTableColumn<Dispatch>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'source_module', header: 'Source', accessor: (row) => row.source_module },
    { key: 'vehicle_id', header: 'Vehicle', render: (row) => vehicleLabel(row.vehicle_id) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={STATUS_VARIANT[row.status]} /> },
    { key: 'dispatch_date', header: 'Date', accessor: (row) => row.dispatch_date, render: (row) => formatDate(row.dispatch_date) },
  ]

  return (
    <div>
      <PageHeader
        parent="Logistics"
        title="Dispatches"
        action={
          can('dispatches.create') && (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="h-4 w-4" />
              New Dispatch
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load dispatches. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: Dispatch) => navigate(`/dispatches/${row.id}`) }]}
          emptyTitle="No dispatches found"
          emptySubtext="Create a dispatch to send stock or deliveries out with a vehicle."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <DispatchFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
