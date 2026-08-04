import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'

import { fetchCashDrawerSessions } from '@/api/pos'
import { fetchBranches } from '@/api/branches'
import { fetchUsers } from '@/api/settings'
import { formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/hooks/use-permissions'
import type { CashDrawerSession } from '@/types/pos'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { ShiftReceiptsDialog } from '@/pages/accounting/ShiftReceiptsDialog'
import { CashDrawerRecoveryDialog } from '@/pages/accounting/CashDrawerRecoveryDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function ShiftReportPage() {
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [userId, setUserId] = useState('all')
  const [branchId, setBranchId] = useState('all')
  const [status, setStatus] = useState<'all' | 'open' | 'closed'>('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [viewingSessionId, setViewingSessionId] = useState<number | null>(null)
  const [recoveringSession, setRecoveringSession] = useState<CashDrawerSession | null>(null)

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: usersPage } = useQuery({ queryKey: ['users-all'], queryFn: () => fetchUsers({ per_page: 100 }) })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['cash-drawer-sessions', page, userId, branchId, status, from, to],
    queryFn: () =>
      fetchCashDrawerSessions({
        page,
        per_page: 15,
        user_id: userId === 'all' ? undefined : Number(userId),
        branch_id: branchId === 'all' ? undefined : Number(branchId),
        status: status === 'all' ? undefined : status,
        from: from || undefined,
        to: to || undefined,
      }),
  })

  const cashierName = (id: number) => usersPage?.data.find((u) => u.id === id)?.name ?? `#${id}`
  const branchName = (id: number) => branches?.find((b) => b.id === id)?.name ?? `#${id}`

  const columns: DataTableColumn<CashDrawerSession>[] = [
    { key: 'user_id', header: 'Cashier', render: (row) => cashierName(row.user_id) },
    { key: 'branch_id', header: 'Branch', render: (row) => branchName(row.branch_id) },
    { key: 'opened_at', header: 'Opened', accessor: (row) => row.opened_at, sortable: true, render: (row) => new Date(row.opened_at).toLocaleString() },
    { key: 'closed_at', header: 'Closed', render: (row) => (row.closed_at ? new Date(row.closed_at).toLocaleString() : '—') },
    { key: 'opening_float', header: 'Opened With', accessor: (row) => row.opening_float, render: (row) => formatCurrency(row.opening_float) },
    { key: 'expected_closing', header: 'Expected', render: (row) => (row.expected_closing !== null ? formatCurrency(row.expected_closing) : '—') },
    { key: 'closing_float', header: 'Closed With', render: (row) => (row.closing_float !== null ? formatCurrency(row.closing_float) : '—') },
    {
      key: 'variance',
      header: 'Variance',
      render: (row) =>
        row.variance !== null ? (
          <span className={row.variance < 0 ? 'font-medium text-danger' : row.variance > 0 ? 'font-medium text-warning' : 'text-foreground'}>
            {formatCurrency(row.variance)}
          </span>
        ) : (
          '—'
        ),
    },
    {
      key: 'outstanding_shortage',
      header: 'Outstanding',
      render: (row) =>
        row.outstanding_shortage > 0 ? (
          <span className="font-medium text-danger">{formatCurrency(row.outstanding_shortage)}</span>
        ) : row.variance !== null && row.variance < 0 ? (
          <span className="text-success">Recovered</span>
        ) : (
          '—'
        ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status === 'open' ? 'Open' : 'Closed'} variant={row.status === 'open' ? 'info' : 'neutral'} />,
    },
  ]

  const canReconcile = can('cash-drawer-sessions.reconcile')

  const rowActions: (row: CashDrawerSession) => DataTableRowAction<CashDrawerSession>[] = (row) => [
    { label: 'View Receipts', onClick: (session) => setViewingSessionId(session.id) },
    ...(canReconcile && row.outstanding_shortage > 0
      ? [{ label: 'Record Recovery', onClick: (session: CashDrawerSession) => setRecoveringSession(session) }]
      : []),
  ]

  return (
    <div>
      <PageHeader parent="Accounting" title="Shift Report" />

      <FilterBar>
        <Select value={userId} onValueChange={(value) => { setUserId(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Cashier" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All cashiers</SelectItem>
            {usersPage?.data.map((user) => (
              <SelectItem key={user.id} value={String(user.id)}>
                {user.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={branchId} onValueChange={(value) => { setBranchId(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Branch" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All branches</SelectItem>
            {branches?.map((branch) => (
              <SelectItem key={branch.id} value={String(branch.id)}>
                {branch.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={status} onValueChange={(value) => { setStatus(value as 'all' | 'open' | 'closed'); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="open">Open</SelectItem>
            <SelectItem value="closed">Closed</SelectItem>
          </SelectContent>
        </Select>

        <Input type="date" value={from} onChange={(event) => { setFrom(event.target.value); setPage(1) }} className="h-8 w-40 rounded-full text-sm" />
        <Input type="date" value={to} onChange={(event) => { setTo(event.target.value); setPage(1) }} className="h-8 w-40 rounded-full text-sm" />
      </FilterBar>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load shift report. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No shifts found"
          emptySubtext="Cash drawer sessions will appear here once cashiers start opening shifts in the POS."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <ShiftReceiptsDialog sessionId={viewingSessionId} onOpenChange={(open) => !open && setViewingSessionId(null)} />
      <CashDrawerRecoveryDialog session={recoveringSession} onOpenChange={(open) => !open && setRecoveringSession(null)} />
    </div>
  )
}
