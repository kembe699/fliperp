import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchJournalEntries } from '@/api/accounting'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { JournalEntry, JournalEntryStatus } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: JournalEntryStatus[] = ['draft', 'posted', 'reversed']
const STATUS_VARIANT: Record<JournalEntryStatus, 'neutral' | 'success' | 'danger'> = {
  draft: 'neutral',
  posted: 'success',
  reversed: 'danger',
}

export function JournalEntriesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['journal-entries', page, status],
    queryFn: () => fetchJournalEntries({ page, per_page: 15, status: status === 'all' ? undefined : (status as JournalEntryStatus) }),
  })

  const columns: DataTableColumn<JournalEntry>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'entry_date', header: 'Date', accessor: (row) => row.entry_date, render: (row) => formatDate(row.entry_date) },
    { key: 'description', header: 'Description', accessor: (row) => row.description ?? '—' },
    {
      key: 'total_debit',
      header: 'Amount',
      accessor: (row) => row.total_debit ?? row.lines.reduce((sum, l) => sum + l.debit, 0),
      render: (row) => formatCurrency(row.total_debit ?? row.lines.reduce((sum, l) => sum + l.debit, 0)),
    },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} /> },
  ]

  return (
    <div>
      <PageHeader
        parent="Accounting"
        title="Journal Entries"
        action={
          <div className="flex gap-2">
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchJournalEntries({ per_page: 10000, status: status === 'all' ? undefined : (status as JournalEntryStatus) })
                exportToCsv('journal-entries.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('journal-entries.create') && (
              <Button onClick={() => navigate('/journal-entries/new')}>
                <Plus className="h-4 w-4" />
                New Journal Entry
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1) }}>
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

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load journal entries. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: JournalEntry) => navigate(`/journal-entries/${row.id}`) }]}
          emptyTitle="No journal entries found"
          emptySubtext="Create a journal entry to record a manual accounting transaction."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
