import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchDispatches, fetchVehicles } from '@/api/logistics'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Dispatch, DispatchStatus } from '@/types/logistics'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DispatchFormDialog } from '@/components/logistics/DispatchFormDialog'

const STATUSES: DispatchStatus[] = ['pending', 'in_transit', 'delivered', 'cancelled']
const STATUS_VARIANT: Record<DispatchStatus, 'warning' | 'info' | 'success' | 'danger'> = {
  pending: 'warning',
  in_transit: 'info',
  delivered: 'success',
  cancelled: 'danger',
}
const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function DispatchesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [formOpen, setFormOpen] = useState(false)
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')

  const { data, isLoading, isError } = useQuery({ queryKey: ['dispatches', 'all'], queryFn: () => fetchDispatches({ per_page: 2000 }) })
  const { data: vehicles } = useQuery({ queryKey: ['vehicles'], queryFn: fetchVehicles })

  const vehicleLabel = (id: number | null) => (id ? vehicles?.find((v) => v.id === id)?.registration_number ?? `#${id}` : 'Unassigned')

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

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
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('dispatches.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('dispatches.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Dispatch
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
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'reference_number', label: 'Reference' }]}
          placeholder="Search dispatches…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load dispatches. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: Dispatch) => navigate(`/dispatches/${row.id}`) }]}
          emptyTitle="No dispatches found"
          emptySubtext="Create a dispatch to send stock or deliveries out with a vehicle."
        />
      )}

      <DispatchFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
