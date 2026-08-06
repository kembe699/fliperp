import { useMemo, useState } from 'react'
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { fetchCrmServices, updateCrmService, deleteCrmService } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmService } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CrmServiceFormDialog } from '@/components/crm/CrmServiceFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function CrmServicesListPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [activeStatus, setActiveStatus] = useState<string>('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editingService, setEditingService] = useState<CrmService | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['crm-services', activeStatus],
    queryFn: () => fetchCrmServices({ per_page: 100, is_active: activeStatus === 'all' ? undefined : activeStatus === 'active' }),
  })

  const filtered = useMemo(() => {
    return (data?.data ?? []).filter(
      (row) => !search || row.name.toLowerCase().includes(search.toLowerCase()) || (row.category ?? '').toLowerCase().includes(search.toLowerCase()),
    )
  }, [data, search])

  const toggleActiveMutation = useMutation({
    mutationFn: (service: CrmService) => updateCrmService(service.id, { is_active: !service.is_active }),
    onSuccess: () => {
      toast.success('Service updated')
      queryClient.invalidateQueries({ queryKey: ['crm-services'] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteCrmService,
    onSuccess: () => {
      toast.success('Service deleted')
      queryClient.invalidateQueries({ queryKey: ['crm-services'] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const columns: DataTableColumn<CrmService>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'category', header: 'Category', accessor: (row) => row.category },
    { key: 'default_price', header: 'Default Price', render: (row) => formatCurrency(row.default_price) },
    {
      key: 'is_active',
      header: 'Status',
      render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} />,
    },
  ]

  const rowActions: (row: CrmService) => DataTableRowAction<CrmService>[] = (row) => [
    ...(can('crm-services.update')
      ? [
          { label: 'Edit', onClick: (s: CrmService) => { setEditingService(s); setFormOpen(true) } },
          { label: row.is_active ? 'Deactivate' : 'Activate', onClick: (s: CrmService) => toggleActiveMutation.mutate(s) },
        ]
      : []),
    ...(can('crm-services.delete')
      ? [{ label: 'Delete', destructive: true, onClick: (s: CrmService) => deleteMutation.mutate(s.id) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Services"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('crm-services.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('crm-services.create') && (
              <Button
                onClick={() => {
                  setEditingService(null)
                  setFormOpen(true)
                }}
              >
                <Plus className="h-4 w-4" />
                New Service
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={activeStatus} onValueChange={setActiveStatus}>
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
          options={[
            { value: 'name', label: 'Name' },
            { value: 'category', label: 'Category' },
          ]}
          placeholder="Search services…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">
          Could not load services. Please try again.
        </p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No services found"
          emptySubtext="Create a service to start building your CRM catalog."
        />
      )}

      <CrmServiceFormDialog open={formOpen} onOpenChange={setFormOpen} service={editingService} />
    </div>
  )
}
