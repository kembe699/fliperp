import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchMeProjects } from '@/api/me'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { MeProject, MeProjectStatus } from '@/types/me'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MeProjectFormDialog } from '@/components/me/MeProjectFormDialog'

const STATUSES: MeProjectStatus[] = ['planned', 'ongoing', 'completed']
const STATUS_VARIANT: Record<MeProjectStatus, 'neutral' | 'info' | 'success'> = {
  planned: 'neutral',
  ongoing: 'info',
  completed: 'success',
}
const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function MeProjectsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [formOpen, setFormOpen] = useState(false)
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')

  const { data, isLoading, isError } = useQuery({ queryKey: ['me-projects', 'all'], queryFn: () => fetchMeProjects({ per_page: 500 }) })

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

  const columns: DataTableColumn<MeProject>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'start_date', header: 'Start', accessor: (row) => row.start_date, render: (row) => formatDate(row.start_date) },
    { key: 'end_date', header: 'End', render: (row) => (row.end_date ? formatDate(row.end_date) : '—') },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} /> },
  ]

  return (
    <div>
      <PageHeader
        parent="M&E"
        title="Projects"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('me-projects.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('me-projects.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Project
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
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search M&E projects…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load M&E projects. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: MeProject) => navigate(`/me-projects/${row.id}`) }]}
          emptyTitle="No M&E projects found"
          emptySubtext="Create a project to start tracking indicators and activities."
        />
      )}

      <MeProjectFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
