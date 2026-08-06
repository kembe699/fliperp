import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchPayrollRuns } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { PayrollRun, PayrollRunStatus } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { PayrollRunFormDialog } from '@/components/hr/PayrollRunFormDialog'

const STATUSES: PayrollRunStatus[] = ['draft', 'processed']
const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function PayrollRunsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['payroll-runs', 'all'], queryFn: () => fetchPayrollRuns({ per_page: 2000 }) })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const branchName = (id: number | null) => (id ? branches?.find((b) => b.id === id)?.name ?? `#${id}` : 'All Branches')

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || branchName(row.branch_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search, branches])

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
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('payroll-runs.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('payroll-runs.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Payroll Run
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
          options={[{ value: 'branch', label: 'Branch' }]}
          placeholder="Search payroll runs…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load payroll runs. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: PayrollRun) => navigate(`/payroll-runs/${row.id}`) }]}
          emptyTitle="No payroll runs found"
          emptySubtext="Create a payroll run to process salaries for a pay period."
        />
      )}

      <PayrollRunFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
