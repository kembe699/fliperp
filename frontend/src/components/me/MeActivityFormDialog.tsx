import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createMeActivity } from '@/api/me'
import { fetchEmployees } from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

export function MeActivityFormDialog({ open, onOpenChange, projectId }: { open: boolean; onOpenChange: (open: boolean) => void; projectId: number }) {
  const queryClient = useQueryClient()
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }), enabled: open })

  const [name, setName] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [responsibleEmployeeId, setResponsibleEmployeeId] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      setName('')
      setStartDate('')
      setEndDate('')
      setResponsibleEmployeeId(null)
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createMeActivity({
        me_project_id: projectId,
        name,
        start_date: startDate,
        end_date: endDate || null,
        responsible_employee_id: responsibleEmployeeId ? Number(responsibleEmployeeId) : null,
      }),
    onSuccess: () => {
      toast.success('Activity added')
      queryClient.invalidateQueries({ queryKey: ['me-activities', projectId] })
      queryClient.invalidateQueries({ queryKey: ['me-project-dashboard', projectId] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = name && startDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add Activity</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input value={name} onChange={(event) => setName(event.target.value)} />
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
            <Label>Responsible Employee</Label>
            <SearchableSelect
              options={(employees?.data ?? []).map((employee) => ({ value: String(employee.id), label: `${employee.first_name} ${employee.last_name}` }))}
              value={responsibleEmployeeId}
              onChange={setResponsibleEmployeeId}
              placeholder="Select employee (optional)"
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Add Activity
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
