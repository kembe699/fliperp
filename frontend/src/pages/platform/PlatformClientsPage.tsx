import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { activatePlatformClient, fetchPlatformClients, suspendPlatformClient } from '@/api/platform'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatDate } from '@/lib/format'
import type { Company, CompanyStatus } from '@/types/auth'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { PlatformClientFormDialog } from '@/pages/platform/PlatformClientFormDialog'

const STATUS_VARIANT: Record<CompanyStatus, 'success' | 'danger' | 'warning'> = {
  active: 'success',
  suspended: 'danger',
  pending: 'warning',
}

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function PlatformClientsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState<string>('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['platform-clients', page, status, search],
    queryFn: () =>
      fetchPlatformClients({
        page,
        per_page: 15,
        status: status === 'all' ? undefined : (status as CompanyStatus),
        search: search || undefined,
      }),
  })

  const suspendMutation = useMutation({
    mutationFn: suspendPlatformClient,
    onSuccess: () => {
      toast.success('Client suspended')
      queryClient.invalidateQueries({ queryKey: ['platform-clients'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const activateMutation = useMutation({
    mutationFn: activatePlatformClient,
    onSuccess: () => {
      toast.success('Client activated')
      queryClient.invalidateQueries({ queryKey: ['platform-clients'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Company>[] = [
    { key: 'name', header: 'Company', accessor: (row) => row.name, sortable: true },
    { key: 'client_code', header: 'Client Code', render: (row) => <span className="font-mono text-xs">{row.client_code}</span> },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} className="capitalize" />,
    },
    { key: 'created_at', header: 'Onboarded', accessor: (row) => row.created_at, sortable: true, render: (row) => formatDate(row.created_at) },
  ]

  const rowActions: (row: Company) => DataTableRowAction<Company>[] = (row) => [
    { label: 'View Details', onClick: (c) => navigate(`/platform-admin/clients/${c.id}`) },
    ...(row.status === 'active'
      ? [{ label: 'Suspend', destructive: true, onClick: (c: Company) => suspendMutation.mutate(c.id) }]
      : [{ label: 'Activate', onClick: (c: Company) => activateMutation.mutate(c.id) }]),
  ]

  return (
    <div>
      <PageHeader
        title="Clients"
        action={
          <Button onClick={() => setFormOpen(true)}>
            <Plus className="h-4 w-4" />
            New Client
          </Button>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="suspended">Suspended</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'search', label: 'Company / Client Code' }]}
          placeholder="Search clients…"
          onSearch={(_by, query) => { setSearch(query); setPage(1) }}
          onClear={() => { setSearch(''); setPage(1) }}
        />
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        rowActions={rowActions}
        emptyTitle="No clients found"
        emptySubtext="Onboard a new client to get started."
        page={data?.meta.current_page}
        pageCount={data?.meta.last_page}
        totalRows={data?.meta.total}
        onPageChange={setPage}
      />

      <PlatformClientFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
