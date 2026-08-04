import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createBudgetPeriod } from '@/api/budgeting'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function BudgetPeriodFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()

  const [name, setName] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')

  useEffect(() => {
    if (open) {
      setName('')
      setStartDate('')
      setEndDate('')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () => createBudgetPeriod({ name, start_date: startDate, end_date: endDate }),
    onSuccess: () => {
      toast.success('Budget period created')
      queryClient.invalidateQueries({ queryKey: ['budget-periods'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = name && startDate && endDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New Budget Period</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="FY2026" />
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
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Budget Period
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
