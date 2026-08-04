import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { createPayrollRun } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function PayrollRunFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, enabled: open })

  const [branchId, setBranchId] = useState('all')
  const [periodStart, setPeriodStart] = useState('')
  const [periodEnd, setPeriodEnd] = useState('')

  useEffect(() => {
    if (open) {
      setBranchId('all')
      setPeriodStart('')
      setPeriodEnd('')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createPayrollRun({
        branch_id: branchId === 'all' ? null : Number(branchId),
        period_start: periodStart,
        period_end: periodEnd,
      }),
    onSuccess: (run) => {
      toast.success('Payroll run created')
      queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
      onOpenChange(false)
      navigate(`/payroll-runs/${run.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = periodStart && periodEnd

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New Payroll Run</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Branch</Label>
            <Select value={branchId} onValueChange={setBranchId}>
              <SelectTrigger>
                <SelectValue placeholder="All branches" />
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
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Period Start</Label>
              <Input type="date" value={periodStart} onChange={(event) => setPeriodStart(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Period End</Label>
              <Input type="date" value={periodEnd} onChange={(event) => setPeriodEnd(event.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Payroll Run
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
