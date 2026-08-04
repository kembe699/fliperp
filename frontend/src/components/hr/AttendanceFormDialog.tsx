import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createAttendance, fetchEmployees } from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { AttendanceStatus } from '@/types/hr'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

const STATUSES: AttendanceStatus[] = ['present', 'absent', 'late', 'on_leave']

export function AttendanceFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }), enabled: open })

  const [employeeId, setEmployeeId] = useState<string | null>(null)
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [clockIn, setClockIn] = useState('')
  const [clockOut, setClockOut] = useState('')
  const [status, setStatus] = useState<AttendanceStatus>('present')

  useEffect(() => {
    if (open) {
      setEmployeeId(null)
      setDate(new Date().toISOString().slice(0, 10))
      setClockIn('')
      setClockOut('')
      setStatus('present')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createAttendance({
        employee_id: Number(employeeId),
        date,
        clock_in: clockIn || null,
        clock_out: clockOut || null,
        status,
      }),
    onSuccess: () => {
      toast.success('Attendance recorded')
      queryClient.invalidateQueries({ queryKey: ['attendance'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = employeeId && date && status

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record Attendance</DialogTitle>
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
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Date</Label>
              <Input type="date" value={date} onChange={(event) => setDate(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Status</Label>
              <Select value={status} onValueChange={(value) => setStatus(value as AttendanceStatus)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {STATUSES.map((s) => (
                    <SelectItem key={s} value={s}>
                      {s.replace('_', ' ')}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Clock In</Label>
              <Input type="time" value={clockIn} onChange={(event) => setClockIn(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Clock Out</Label>
              <Input type="time" value={clockOut} onChange={(event) => setClockOut(event.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Record Attendance
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
