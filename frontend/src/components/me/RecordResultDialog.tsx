import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createMeResult } from '@/api/me'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { MeIndicator } from '@/types/me'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function RecordResultDialog({
  open,
  onOpenChange,
  indicator,
  projectId,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  indicator: MeIndicator | null
  projectId: number
}) {
  const queryClient = useQueryClient()

  const [reportingPeriod, setReportingPeriod] = useState('')
  const [actualValue, setActualValue] = useState('')
  const [notes, setNotes] = useState('')

  useEffect(() => {
    if (open) {
      setReportingPeriod('')
      setActualValue('')
      setNotes('')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () => createMeResult({ me_indicator_id: indicator!.id, reporting_period: reportingPeriod, actual_value: Number(actualValue), notes: notes || null }),
    onSuccess: () => {
      toast.success('Result recorded')
      queryClient.invalidateQueries({ queryKey: ['me-results', indicator?.id] })
      queryClient.invalidateQueries({ queryKey: ['me-project-dashboard', projectId] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = reportingPeriod && actualValue

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record Result{indicator ? ` — ${indicator.name}` : ''}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Reporting Period</Label>
            <Input value={reportingPeriod} onChange={(event) => setReportingPeriod(event.target.value)} placeholder="Q1 2026" />
          </div>
          <div className="space-y-1.5">
            <Label>Actual Value{indicator?.unit_of_measure ? ` (${indicator.unit_of_measure})` : ''}</Label>
            <Input type="number" step="0.01" value={actualValue} onChange={(event) => setActualValue(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Notes</Label>
            <Input value={notes} onChange={(event) => setNotes(event.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Record Result
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
