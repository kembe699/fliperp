import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchJournalEntries } from '@/api/accounting'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { JournalEntry, JournalEntryStatus } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-40 text-sm'
const STATUSES: JournalEntryStatus[] = ['draft', 'posted', 'reversed']
const STATUS_VARIANT: Record<JournalEntryStatus, 'neutral' | 'success' | 'danger'> = {
  draft: 'neutral',
  posted: 'success',
  reversed: 'danger',
}

export function JournalEntriesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['journal-entries', 'all'],
    queryFn: () => fetchJournalEntries({ per_page: 2000 }),
  })

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()) || (row.description ?? '').toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

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
            {can('journal-entries.create') && (
              <Button onClick={() => navigate('/journal-entries/new')}>
                <Plus className="h-4 w-4" />
                New Journal Entry
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load journal entries. Please try again.</p>
      ) : (
        <DataTable
          onSearchChange={setSearch}
          toolbarExtra={
            <>
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
            </>
          }
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: JournalEntry) => navigate(`/journal-entries/${row.id}`) }]}
          emptyTitle="No journal entries found"
          emptySubtext="Create a journal entry to record a manual accounting transaction."
        />
      )}
    </div>
  )
}
