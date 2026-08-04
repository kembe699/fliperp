import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createLeaveRequest, fetchEmployees, fetchLeaveTypes } from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

export function LeaveRequestFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }), enabled: open })
  const { data: leaveTypes } = useQuery({ queryKey: ['leave-types'], queryFn: fetchLeaveTypes, enabled: open })

  const [employeeId, setEmployeeId] = useState<string | null>(null)
  const [leaveTypeId, setLeaveTypeId] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [reason, setReason] = useState('')

  useEffect(() => {
    if (open) {
      setEmployeeId(null)
      setLeaveTypeId('')
      setStartDate('')
      setEndDate('')
      setReason('')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createLeaveRequest({
        employee_id: Number(employeeId),
        leave_type_id: Number(leaveTypeId),
        start_date: startDate,
        end_date: endDate,
        reason: reason || null,
      }),
    onSuccess: () => {
      toast.success('Leave request submitted')
      queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = employeeId && leaveTypeId && startDate && endDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New Leave Request</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Employee</Label>
            <SearchableSelect
              options={(employees?.data ?? []).map((employee) => ({ value: String(employee.id), label: `${employee.first_name} ${employee.last_name}`, sublabel: employee.employee_code }))}
              value={employeeId}
              onChange={setEmployeeId}
              placeholder="Select employee"
            />
          </div>
          <div className="space-y-1.5">
            <Label>Leave Type</Label>
            <Select value={leaveTypeId} onValueChange={setLeaveTypeId}>
              <SelectTrigger>
                <SelectValue placeholder="Select leave type" />
              </SelectTrigger>
              <SelectContent>
                {leaveTypes?.map((leaveType) => (
                  <SelectItem key={leaveType.id} value={String(leaveType.id)}>
                    {leaveType.name} ({leaveType.days_per_year} days/yr)
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
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
          <div className="space-y-1.5">
            <Label>Reason</Label>
            <Input value={reason} onChange={(event) => setReason(event.target.value)} />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Submit Request
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
