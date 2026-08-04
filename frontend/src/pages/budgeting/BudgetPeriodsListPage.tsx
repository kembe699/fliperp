import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchBudgetPeriods } from '@/api/budgeting'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { BudgetPeriod, BudgetPeriodStatus } from '@/types/budgeting'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { BudgetPeriodFormDialog } from '@/components/budgeting/BudgetPeriodFormDialog'

const STATUS_VARIANT: Record<BudgetPeriodStatus, 'neutral' | 'success' | 'info'> = {
  draft: 'neutral',
  active: 'success',
  closed: 'info',
}

export function BudgetPeriodsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['budget-periods', page], queryFn: () => fetchBudgetPeriods({ page, per_page: 15 }) })

  const columns: DataTableColumn<BudgetPeriod>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'start_date', header: 'Start', accessor: (row) => row.start_date, render: (row) => formatDate(row.start_date) },
    { key: 'end_date', header: 'End', accessor: (row) => row.end_date, render: (row) => formatDate(row.end_date) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} /> },
  ]

  return (
    <div>
      <PageHeader
        parent="Budgeting"
        title="Budget Periods"
        action={
          can('budget-periods.create') && (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="h-4 w-4" />
              New Budget Period
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load budget periods. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: BudgetPeriod) => navigate(`/budget-periods/${row.id}`) }]}
          emptyTitle="No budget periods found"
          emptySubtext="Create a budget period to start planning and tracking spend."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <BudgetPeriodFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
