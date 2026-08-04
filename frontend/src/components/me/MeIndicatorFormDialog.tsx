import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createMeIndicator } from '@/api/me'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function MeIndicatorFormDialog({ open, onOpenChange, projectId }: { open: boolean; onOpenChange: (open: boolean) => void; projectId: number }) {
  const queryClient = useQueryClient()

  const [name, setName] = useState('')
  const [unit, setUnit] = useState('')
  const [target, setTarget] = useState('')
  const [baseline, setBaseline] = useState('0')

  useEffect(() => {
    if (open) {
      setName('')
      setUnit('')
      setTarget('')
      setBaseline('0')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () => createMeIndicator({ me_project_id: projectId, name, unit_of_measure: unit || null, target_value: Number(target), baseline_value: Number(baseline) }),
    onSuccess: () => {
      toast.success('Indicator added')
      queryClient.invalidateQueries({ queryKey: ['me-indicators', projectId] })
      queryClient.invalidateQueries({ queryKey: ['me-project-dashboard', projectId] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = name && target

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add Indicator</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input value={name} onChange={(event) => setName(event.target.value)} />
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label>Unit</Label>
              <Input value={unit} onChange={(event) => setUnit(event.target.value)} placeholder="people, %, km" />
            </div>
            <div className="space-y-1.5">
              <Label>Baseline</Label>
              <Input type="number" step="0.01" value={baseline} onChange={(event) => setBaseline(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Target</Label>
              <Input type="number" step="0.01" value={target} onChange={(event) => setTarget(event.target.value)} />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Add Indicator
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
