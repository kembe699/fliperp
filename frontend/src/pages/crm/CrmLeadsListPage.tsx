import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { fetchCrmLeads, deleteCrmLead } from '@/api/crm'
import { fetchUsers } from '@/api/settings'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmLead, CrmLeadStatus } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CrmLeadFormDialog } from '@/components/crm/CrmLeadFormDialog'
import { ConvertLeadDialog } from '@/components/crm/ConvertLeadDialog'

const STATUS_VARIANT: Record<CrmLeadStatus, 'info' | 'success' | 'neutral'> = {
  open: 'info',
  converted: 'success',
  disqualified: 'neutral',
}

const SOURCE_OPTIONS = ['referral', 'website', 'cold_call', 'social_media', 'event', 'other']

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function CrmLeadsListPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState<string>('all')
  const [source, setSource] = useState<string>('all')
  const [assignedTo, setAssignedTo] = useState<string>('all')
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editingLead, setEditingLead] = useState<CrmLead | null>(null)
  const [convertingLead, setConvertingLead] = useState<CrmLead | null>(null)

  const { data: users } = useQuery({ queryKey: ['settings-users-all'], queryFn: () => fetchUsers({ per_page: 100 }) })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['crm-leads', page, status, source, assignedTo, search],
    queryFn: () =>
      fetchCrmLeads({
        page,
        per_page: 15,
        status: status === 'all' ? undefined : (status as CrmLeadStatus),
        source: source === 'all' ? undefined : source,
        assigned_to: assignedTo === 'all' ? undefined : Number(assignedTo),
        search: search || undefined,
      }),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteCrmLead,
    onSuccess: () => {
      toast.success('Lead deleted')
      queryClient.invalidateQueries({ queryKey: ['crm-leads'] })
    },
    onError: () => toast.error('Could not delete this lead'),
  })

  const columns: DataTableColumn<CrmLead>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'company_name', header: 'Company', accessor: (row) => row.company_name },
    { key: 'source', header: 'Source', render: (row) => (row.source ? row.source.replace('_', ' ') : '—') },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} />,
    },
    { key: 'assigned_to', header: 'Assigned To', render: (row) => row.assigned_to?.name ?? 'Unassigned' },
    {
      key: 'created_at',
      header: 'Created',
      accessor: (row) => row.created_at,
      sortable: true,
      render: (row) => formatDate(row.created_at),
    },
  ]

  const rowActions: (row: CrmLead) => DataTableRowAction<CrmLead>[] = (row) => [
    { label: 'View Details', onClick: (l: CrmLead) => navigate(`/crm/leads/${l.id}`) },
    ...(row.status === 'open' && can('crm-leads.update')
      ? [{ label: 'Edit', onClick: (l: CrmLead) => { setEditingLead(l); setFormOpen(true) } }]
      : []),
    ...(row.status === 'open' && can('crm-leads.convert')
      ? [{ label: 'Convert to Customer', onClick: (l: CrmLead) => setConvertingLead(l) }]
      : []),
    ...(can('crm-leads.delete')
      ? [{ label: 'Delete', destructive: true, onClick: (l: CrmLead) => deleteMutation.mutate(l.id) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Leads"
        action={
          can('crm-leads.create') && (
            <Button
              onClick={() => {
                setEditingLead(null)
                setFormOpen(true)
              }}
            >
              <Plus className="h-4 w-4" />
              New Lead
            </Button>
          )
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="open">Open</SelectItem>
            <SelectItem value="converted">Converted</SelectItem>
            <SelectItem value="disqualified">Disqualified</SelectItem>
          </SelectContent>
        </Select>

        <Select value={source} onValueChange={(value) => { setSource(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Source" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All sources</SelectItem>
            {SOURCE_OPTIONS.map((s) => (
              <SelectItem key={s} value={s}>
                {s.replace('_', ' ')}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={assignedTo} onValueChange={(value) => { setAssignedTo(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Assigned To" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Everyone</SelectItem>
            {users?.data.map((u) => (
              <SelectItem key={u.id} value={String(u.id)}>
                {u.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'name', label: 'Name' },
            { value: 'company_name', label: 'Company' },
            { value: 'email', label: 'Email' },
            { value: 'phone', label: 'Phone' },
          ]}
          placeholder="Search leads…"
          onSearch={(_by, query) => { setSearch(query); setPage(1) }}
          onClear={() => { setSearch(''); setPage(1) }}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">
          Could not load leads. Please try again.
        </p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No leads found"
          emptySubtext="Try adjusting your filters, or create a new lead to get started."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <CrmLeadFormDialog open={formOpen} onOpenChange={setFormOpen} lead={editingLead} />
      <ConvertLeadDialog open={!!convertingLead} onOpenChange={(open) => !open && setConvertingLead(null)} lead={convertingLead} />
    </div>
  )
}
