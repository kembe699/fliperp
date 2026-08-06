import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { approveLeaveRequest, fetchEmployees, fetchLeaveRequests, fetchLeaveTypes, rejectLeaveRequest } from '@/api/hr'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { LeaveRequest, LeaveRequestStatus } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { LeaveRequestFormDialog } from '@/components/hr/LeaveRequestFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: LeaveRequestStatus[] = ['pending', 'approved', 'rejected']
const STATUS_VARIANT: Record<LeaveRequestStatus, 'warning' | 'success' | 'danger'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
}

export function LeaveRequestsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['leave-requests', 'all'],
    queryFn: () => fetchLeaveRequests({ per_page: 2000 }),
  })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })
  const { data: leaveTypes } = useQuery({ queryKey: ['leave-types'], queryFn: fetchLeaveTypes })

  const employeeName = (id: number) => {
    const employee = employees?.data.find((e) => e.id === id)
    return employee ? `${employee.first_name} ${employee.last_name}` : `#${id}`
  }
  const leaveTypeName = (id: number) => leaveTypes?.find((lt) => lt.id === id)?.name ?? `#${id}`

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => !search || employeeName(row.employee_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search, employees])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['leave-requests'] })

  const approveMutation = useMutation({
    mutationFn: approveLeaveRequest,
    onSuccess: () => {
      toast.success('Leave request approved')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const rejectMutation = useMutation({
    mutationFn: rejectLeaveRequest,
    onSuccess: () => {
      toast.success('Leave request rejected')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<LeaveRequest>[] = [
    { key: 'employee_id', header: 'Employee', render: (row) => employeeName(row.employee_id) },
    { key: 'leave_type_id', header: 'Leave Type', render: (row) => leaveTypeName(row.leave_type_id) },
    { key: 'start_date', header: 'Start', accessor: (row) => row.start_date, render: (row) => formatDate(row.start_date) },
    { key: 'end_date', header: 'End', accessor: (row) => row.end_date, render: (row) => formatDate(row.end_date) },
    { key: 'days_count', header: 'Days', accessor: (row) => row.days_count },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} /> },
  ]

  const rowActions: (row: LeaveRequest) => DataTableRowAction<LeaveRequest>[] = (row) =>
    row.status === 'pending'
      ? [
          ...(can('leave-requests.approve') ? [{ label: 'Approve', onClick: (lr: LeaveRequest) => approveMutation.mutate(lr.id) }] : []),
          ...(can('leave-requests.reject') ? [{ label: 'Reject', destructive: true, onClick: (lr: LeaveRequest) => rejectMutation.mutate(lr.id) }] : []),
        ]
      : []

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Leave Requests"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('leave-requests.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('leave-requests.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Leave Request
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
          options={[{ value: 'employee', label: 'Employee' }]}
          placeholder="Search leave requests…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load leave requests. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No leave requests found"
          emptySubtext="Leave requests submitted by employees will appear here."
        />
      )}

      <LeaveRequestFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
