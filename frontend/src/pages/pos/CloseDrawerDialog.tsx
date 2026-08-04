import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { closeCashDrawer } from '@/api/pos'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/format'
import type { CashDrawerSession } from '@/types/pos'

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface CloseDrawerDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  session: CashDrawerSession
}

export function CloseDrawerDialog({ open, onOpenChange, session }: CloseDrawerDialogProps) {
  const queryClient = useQueryClient()
  const [closingFloat, setClosingFloat] = useState('')
  const [closedSession, setClosedSession] = useState<CashDrawerSession | null>(null)

  const mutation = useMutation({
    mutationFn: () => closeCashDrawer({ closing_float: Number(closingFloat) }),
    onSuccess: (closed) => {
      toast.success('Cash drawer closed')
      setClosedSession(closed)
      queryClient.invalidateQueries({ queryKey: ['cash-drawer-current'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setClosingFloat('')
      setClosedSession(null)
    }
    onOpenChange(next)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        {closedSession ? (
          <>
            <DialogHeader>
              <DialogTitle>Shift Closed</DialogTitle>
              <DialogDescription>Here's the summary for this session.</DialogDescription>
            </DialogHeader>
            <div className="space-y-1.5 text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Opening Float</span>
                <span className="text-foreground">{formatCurrency(closedSession.opening_float)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Expected Closing</span>
                <span className="text-foreground">{closedSession.expected_closing !== null ? formatCurrency(closedSession.expected_closing) : '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Counted Closing</span>
                <span className="text-foreground">{closedSession.closing_float !== null ? formatCurrency(closedSession.closing_float) : '—'}</span>
              </div>
              <div className="flex justify-between border-t border-border pt-1.5 font-semibold">
                <span className={closedSession.variance && closedSession.variance < 0 ? 'text-danger' : 'text-foreground'}>Variance</span>
                <span className={closedSession.variance && closedSession.variance < 0 ? 'text-danger' : 'text-foreground'}>
                  {closedSession.variance !== null ? formatCurrency(closedSession.variance) : '—'}
                </span>
              </div>
            </div>
            <DialogFooter>
              <Button onClick={() => handleOpenChange(false)}>Done</Button>
            </DialogFooter>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle>Close Shift</DialogTitle>
              <DialogDescription>
                Opened {new Date(session.opened_at).toLocaleString()} with an opening float of {formatCurrency(session.opening_float)}.
              </DialogDescription>
            </DialogHeader>
            <form
              onSubmit={(event) => {
                event.preventDefault()
                mutation.mutate()
              }}
              className="space-y-4"
            >
              <div className="space-y-1.5">
                <Label htmlFor="closing_float">Counted Closing Float</Label>
                <Input
                  id="closing_float"
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  autoFocus
                  value={closingFloat}
                  onChange={(event) => setClosingFloat(event.target.value)}
                />
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={mutation.isPending}>
                  {mutation.isPending ? 'Closing…' : 'Close Shift'}
                </Button>
              </DialogFooter>
            </form>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
