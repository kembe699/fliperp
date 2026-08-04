import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { approveLeaveRequest, fetchEmployees, fetchLeaveRequests, fetchLeaveTypes, rejectLeaveRequest } from '@/api/hr'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { LeaveRequest, LeaveRequestStatus } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
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

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['leave-requests', page, status],
    queryFn: () => fetchLeaveRequests({ page, per_page: 15, status: status === 'all' ? undefined : (status as LeaveRequestStatus) }),
  })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })
  const { data: leaveTypes } = useQuery({ queryKey: ['leave-types'], queryFn: fetchLeaveTypes })

  const employeeName = (id: number) => {
    const employee = employees?.data.find((e) => e.id === id)
    return employee ? `${employee.first_name} ${employee.last_name}` : `#${id}`
  }
  const leaveTypeName = (id: number) => leaveTypes?.find((lt) => lt.id === id)?.name ?? `#${id}`

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
          can('leave-requests.create') && (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="h-4 w-4" />
              New Leave Request
            </Button>
          )
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
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load leave requests. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No leave requests found"
          emptySubtext="Leave requests submitted by employees will appear here."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <LeaveRequestFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
