import { useEffect, useState } from 'react'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

interface LostReasonDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onConfirm: (reason: string) => void
  isSubmitting?: boolean
}

export function LostReasonDialog({ open, onOpenChange, onConfirm, isSubmitting }: LostReasonDialogProps) {
  const [reason, setReason] = useState('')

  useEffect(() => {
    if (open) setReason('')
  }, [open])

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Why was this deal lost?</DialogTitle>
        </DialogHeader>

        <div className="space-y-1.5">
          <Label htmlFor="lost-reason">Reason</Label>
          <Textarea
            id="lost-reason"
            rows={3}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            placeholder="e.g. Went with a competitor, budget cut, no response…"
            autoFocus
          />
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!reason.trim() || isSubmitting} onClick={() => onConfirm(reason.trim())}>
            Mark as Closed Lost
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
