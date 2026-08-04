import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchPayrollRuns } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { PayrollRun } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { PayrollRunFormDialog } from '@/components/hr/PayrollRunFormDialog'

export function PayrollRunsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['payroll-runs', page], queryFn: () => fetchPayrollRuns({ page, per_page: 15 }) })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const branchName = (id: number | null) => (id ? branches?.find((b) => b.id === id)?.name ?? `#${id}` : 'All Branches')

  const columns: DataTableColumn<PayrollRun>[] = [
    { key: 'period_start', header: 'Period', render: (row) => `${formatDate(row.period_start)} – ${formatDate(row.period_end)}` },
    { key: 'branch_id', header: 'Branch', render: (row) => branchName(row.branch_id) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={row.status === 'processed' ? 'success' : 'neutral'} /> },
  ]

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Payroll Runs"
        action={
          can('payroll-runs.create') && (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="h-4 w-4" />
              New Payroll Run
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load payroll runs. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: PayrollRun) => navigate(`/payroll-runs/${row.id}`) }]}
          emptyTitle="No payroll runs found"
          emptySubtext="Create a payroll run to process salaries for a pay period."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <PayrollRunFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
