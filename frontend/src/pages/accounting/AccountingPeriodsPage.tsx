import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Lock, Plus } from 'lucide-react'

import { closeAccountingPeriod, createAccountingPeriod, fetchAccountingPeriods } from '@/api/accounting'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { AccountingPeriod, AccountingPeriodStatus } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const STATUS_VARIANT: Record<AccountingPeriodStatus, 'success' | 'neutral'> = {
  open: 'success',
  closed: 'neutral',
}

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function AccountingPeriodsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [status, setStatus] = useState<string>('all')
  const [search, setSearch] = useState('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['accounting-periods', 'all'],
    queryFn: () => fetchAccountingPeriods({ per_page: 500 }),
  })

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

  const [formOpen, setFormOpen] = useState(false)
  const [name, setName] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')

  const [blockers, setBlockers] = useState<string[] | null>(null)

  useEffect(() => {
    if (formOpen) {
      setName('')
      setStartDate('')
      setEndDate('')
    }
  }, [formOpen])

  const createMutation = useMutation({
    mutationFn: () => createAccountingPeriod({ name, start_date: startDate, end_date: endDate }),
    onSuccess: () => {
      toast.success('Accounting period created')
      queryClient.invalidateQueries({ queryKey: ['accounting-periods'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const closeMutation = useMutation({
    mutationFn: closeAccountingPeriod,
    onSuccess: () => {
      toast.success('Accounting period closed')
      queryClient.invalidateQueries({ queryKey: ['accounting-periods'] })
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      if (info.errors?.draft_entries) {
        setBlockers(info.errors.draft_entries)
      } else {
        toast.error(info.message)
      }
    },
  })

  const columns: DataTableColumn<AccountingPeriod>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'start_date', header: 'Start', accessor: (row) => row.start_date, render: (row) => formatDate(row.start_date) },
    { key: 'end_date', header: 'End', accessor: (row) => row.end_date, render: (row) => formatDate(row.end_date) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} /> },
  ]

  const rowActions: (row: AccountingPeriod) => DataTableRowAction<AccountingPeriod>[] = (row) =>
    row.status === 'open' && can('accounting-periods.close')
      ? [{ label: 'Close Period', onClick: (p: AccountingPeriod) => closeMutation.mutate(p.id) }]
      : []

  return (
    <div>
      <PageHeader
        parent="Accounting"
        title="Accounting Periods"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('accounting-periods.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('accounting-periods.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Period
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
            <SelectItem value="open">Open</SelectItem>
            <SelectItem value="closed">Closed</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search accounting periods…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {blockers && (
        <div className="mb-4 rounded-xl border border-danger/30 bg-danger/5 p-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-danger">
            <Lock className="h-4 w-4" />
            This period cannot be closed yet
          </div>
          <ul className="ml-6 list-disc space-y-1 text-sm text-foreground">
            {blockers.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
          <Button variant="outline" size="sm" className="mt-3" onClick={() => setBlockers(null)}>
            Dismiss
          </Button>
        </div>
      )}

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load accounting periods. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No accounting periods found"
          emptySubtext="Create an accounting period to gate when journal entries can be posted."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>New Accounting Period</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="January 2026" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Start Date</Label>
                <Input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>End Date</Label>
                <Input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || !startDate || !endDate || createMutation.isPending} onClick={() => createMutation.mutate()}>
              Create Period
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
