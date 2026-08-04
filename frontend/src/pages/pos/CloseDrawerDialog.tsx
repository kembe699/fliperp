import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { closeCashDrawer, fetchCurrentCashDrawerSession } from '@/api/pos'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
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

  // Re-fetched fresh every time the dialog opens (not just reused from
  // whatever the terminal page loaded with) so the expected-closing figure
  // reflects sales rung up right up until this moment, before the cashier
  // types anything in.
  const { data: liveSession, isLoading: liveLoading } = useQuery({
    queryKey: ['cash-drawer-current-live'],
    queryFn: fetchCurrentCashDrawerSession,
    enabled: open && !closedSession,
  })
  const expectedClosing = liveSession?.expected_closing ?? session.expected_closing

  const mutation = useMutation({
    mutationFn: () => closeCashDrawer({ closing_float: Number(closingFloat) }),
    onSuccess: (closed) => {
      toast.success('Cash drawer closed')
      setClosedSession(closed)
      // Deliberately NOT invalidating ['cash-drawer-current'] here — the POS
      // terminal page renders a completely different screen (Open Drawer)
      // the moment that query resolves to null, which would unmount this
      // dialog mid-summary. It's invalidated instead when the cashier
      // dismisses the summary via "Done" below.
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      if (closedSession) {
        queryClient.invalidateQueries({ queryKey: ['cash-drawer-current'] })
      }
      setClosingFloat('')
      setClosedSession(null)
    }
    onOpenChange(next)
  }

  const variance = closedSession?.variance ?? null
  const varianceLabel = variance === null ? '' : variance > 0 ? 'Over' : variance < 0 ? 'Short' : 'Exact'

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
                <span className={variance && variance < 0 ? 'text-danger' : 'text-foreground'}>Variance {variance !== null && variance !== 0 ? `(${varianceLabel})` : ''}</span>
                <span className={variance && variance < 0 ? 'text-danger' : 'text-foreground'}>
                  {variance !== null ? formatCurrency(variance) : '—'}
                </span>
              </div>
              {variance !== null && variance !== 0 && (
                <p className="pt-1 text-xs text-muted-foreground">
                  {variance < 0
                    ? "The till was short — the shift is still closed and recorded; this variance is here for review, it doesn't block anything."
                    : 'The till had more than expected — recorded for review.'}
                </p>
              )}
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
              <div className="rounded-lg border border-border bg-muted/40 px-3 py-2">
                <p className="text-xs font-semibold uppercase text-muted-foreground">Expected in Drawer</p>
                <p className="text-lg font-bold text-foreground">
                  {expectedClosing !== null && expectedClosing !== undefined ? formatCurrency(expectedClosing) : liveLoading ? 'Calculating…' : '—'}
                </p>
                <p className="text-xs text-muted-foreground">Opening float plus all cash payments taken this shift. Count your drawer against this before entering below.</p>
              </div>
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
                <p className="text-xs text-muted-foreground">
                  If this doesn't match the expected amount, that's fine — the shift still closes and the difference is recorded as a variance for review.
                </p>
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
