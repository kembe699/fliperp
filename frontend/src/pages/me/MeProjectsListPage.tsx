import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchMeProjects } from '@/api/me'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { MeProject, MeProjectStatus } from '@/types/me'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { MeProjectFormDialog } from '@/components/me/MeProjectFormDialog'

const STATUS_VARIANT: Record<MeProjectStatus, 'neutral' | 'info' | 'success'> = {
  planned: 'neutral',
  ongoing: 'info',
  completed: 'success',
}

export function MeProjectsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['me-projects', page], queryFn: () => fetchMeProjects({ page, per_page: 15 }) })

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
          can('me-projects.create') && (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="h-4 w-4" />
              New Project
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load M&E projects. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: MeProject) => navigate(`/me-projects/${row.id}`) }]}
          emptyTitle="No M&E projects found"
          emptySubtext="Create a project to start tracking indicators and activities."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <MeProjectFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
