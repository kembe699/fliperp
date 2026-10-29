import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createMeProject, updateMeProject } from '@/api/me'
import { fetchBudgetPeriods } from '@/api/budgeting'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { MeProject, MeProjectStatus } from '@/types/me'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/**
 * Creates a project, or edits `project` when given. The budget period is what
 * connects a project to the accounts: its dashboard compares that period's
 * budget lines with posted ledger activity.
 */
export function MeProjectFormDialog({ open, onOpenChange, project }: { open: boolean; onOpenChange: (open: boolean) => void; project?: MeProject | null }) {
  const queryClient = useQueryClient()
  const isEdit = !!project

  const { data: budgetPeriods } = useQuery({ queryKey: ['budget-periods', 'all'], queryFn: () => fetchBudgetPeriods({ per_page: 100 }), enabled: open })

  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [status, setStatus] = useState<MeProjectStatus>('planned')
  const [budgetPeriodId, setBudgetPeriodId] = useState('none')

  useEffect(() => {
    if (open) {
      setName(project?.name ?? '')
      setDescription(project?.description ?? '')
      setStartDate(project?.start_date ?? '')
      setEndDate(project?.end_date ?? '')
      setStatus(project?.status ?? 'planned')
      setBudgetPeriodId(project?.budget_period_id ? String(project.budget_period_id) : 'none')
    }
  }, [open, project])

  const mutation = useMutation({
    mutationFn: () => {
      const values = {
        name,
        description: description || null,
        start_date: startDate,
        end_date: endDate || null,
        status,
        budget_period_id: budgetPeriodId === 'none' ? null : Number(budgetPeriodId),
      }
      return project ? updateMeProject(project.id, values) : createMeProject(values)
    },
    onSuccess: () => {
      toast.success(isEdit ? 'Project updated' : 'Project created')
      queryClient.invalidateQueries({ queryKey: ['me-projects'] })
      if (project) {
        queryClient.invalidateQueries({ queryKey: ['me-project', project.id] })
        queryClient.invalidateQueries({ queryKey: ['me-project-dashboard', project.id] })
      }
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = name && startDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit M&E Project' : 'New M&E Project'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input value={name} onChange={(event) => setName(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Description</Label>
            <Input value={description} onChange={(event) => setDescription(event.target.value)} />
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
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Status</Label>
              <Select value={status} onValueChange={(value) => setStatus(value as MeProjectStatus)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="planned">Planned</SelectItem>
                  <SelectItem value="ongoing">Ongoing</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Budget period</Label>
              <Select value={budgetPeriodId} onValueChange={setBudgetPeriodId}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Not linked</SelectItem>
                  {budgetPeriods?.data.map((period) => (
                    <SelectItem key={period.id} value={String(period.id)}>
                      {period.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <p className="text-xs text-muted-foreground">Linking a budget period shows the project's budget against actual spending from the accounts.</p>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            {isEdit ? 'Save Changes' : 'Create Project'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
