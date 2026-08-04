import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CalendarDays } from 'lucide-react'

import { createPortalLeaveRequest, fetchPortalLeaveRequests, fetchPortalLeaveSummary, fetchPortalLeaveTypes } from '@/api/employee-portal'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatDate } from '@/lib/format'
import type { LeaveRequestStatus } from '@/types/employee-portal'

import { PortalStatCard } from '@/components/portal/PortalStatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const STATUS_VARIANT: Record<LeaveRequestStatus, 'success' | 'warning' | 'danger'> = {
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
}

export function PortalLeavePage() {
  const queryClient = useQueryClient()

  const { data: summary } = useQuery({ queryKey: ['portal-leave-summary'], queryFn: fetchPortalLeaveSummary })
  const { data: leaveTypes } = useQuery({ queryKey: ['portal-leave-types'], queryFn: fetchPortalLeaveTypes })
  const { data: history, isLoading: historyLoading } = useQuery({
    queryKey: ['portal-leave-requests'],
    queryFn: () => fetchPortalLeaveRequests({ per_page: 50 }),
  })

  const [formOpen, setFormOpen] = useState(false)
  const [leaveTypeId, setLeaveTypeId] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [reason, setReason] = useState('')

  const leaveTypeName = (id: number) => leaveTypes?.find((type) => type.id === id)?.name ?? `#${id}`

  const submitMutation = useMutation({
    mutationFn: () =>
      createPortalLeaveRequest({
        leave_type_id: Number(leaveTypeId),
        start_date: startDate,
        end_date: endDate,
        reason: reason || null,
      }),
    onSuccess: () => {
      toast.success('Leave request submitted')
      setFormOpen(false)
      setLeaveTypeId('')
      setStartDate('')
      setEndDate('')
      setReason('')
      queryClient.invalidateQueries({ queryKey: ['portal-leave-requests'] })
      queryClient.invalidateQueries({ queryKey: ['portal-leave-summary'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = leaveTypeId && startDate && endDate

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-lg font-semibold text-foreground">Leave</h1>
        <p className="text-sm text-muted-foreground">Your leave balance and requests.</p>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <PortalStatCard label="Entitled" value={summary?.total_entitled_days ?? '—'} />
        <PortalStatCard label="Taken" value={summary?.total_taken_days ?? '—'} />
        <PortalStatCard label="Remaining" value={summary?.total_remaining_days ?? '—'} />
        <PortalStatCard
          label="Last Leave"
          value={summary?.last_approved_leave_date ? formatDate(summary.last_approved_leave_date) : '—'}
          icon={<CalendarDays className="h-3.5 w-3.5" />}
        />
      </div>

      {!formOpen ? (
        <Button className="h-12 w-full text-base" onClick={() => setFormOpen(true)}>
          Request Leave
        </Button>
      ) : (
        <div className="space-y-4 rounded-xl border border-border bg-white p-4">
          <p className="text-sm font-semibold text-foreground">New Leave Request</p>
          <div className="space-y-1.5">
            <Label>Leave Type</Label>
            <Select value={leaveTypeId} onValueChange={setLeaveTypeId}>
              <SelectTrigger>
                <SelectValue placeholder="Select leave type" />
              </SelectTrigger>
              <SelectContent>
                {leaveTypes?.map((type) => (
                  <SelectItem key={type.id} value={String(type.id)}>
                    {type.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>Start Date</Label>
              <Input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>End Date</Label>
              <Input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} min={startDate || undefined} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Reason (optional)</Label>
            <Textarea value={reason} onChange={(event) => setReason(event.target.value)} rows={3} />
          </div>
          <div className="flex gap-2">
            <Button variant="outline" className="flex-1" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button className="flex-1" disabled={!canSubmit || submitMutation.isPending} onClick={() => submitMutation.mutate()}>
              Submit
            </Button>
          </div>
        </div>
      )}

      <div>
        <p className="mb-2 text-sm font-semibold text-foreground">History</p>
        {historyLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
        {!historyLoading && (history?.data.length ?? 0) === 0 && <p className="text-sm text-muted-foreground">No leave requests yet.</p>}
        <div className="space-y-2">
          {history?.data.map((request) => (
            <div key={request.id} className="rounded-xl border border-border bg-white p-3">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-foreground">{leaveTypeName(request.leave_type_id)}</p>
                <StatusBadge label={request.status} variant={STATUS_VARIANT[request.status]} />
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                {formatDate(request.start_date)} – {formatDate(request.end_date)} · {request.days_count} day{request.days_count === 1 ? '' : 's'}
              </p>
              {request.reason && <p className="mt-1 text-xs text-muted-foreground">{request.reason}</p>}
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
