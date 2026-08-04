import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { recordCashDrawerRecovery } from '@/api/pos'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import type { CashDrawerSession } from '@/types/pos'

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

interface CashDrawerRecoveryDialogProps {
  session: CashDrawerSession | null
  onOpenChange: (open: boolean) => void
}

export function CashDrawerRecoveryDialog({ session, onOpenChange }: CashDrawerRecoveryDialogProps) {
  const queryClient = useQueryClient()
  const [amount, setAmount] = useState('')
  const [notes, setNotes] = useState('')
  const [amountError, setAmountError] = useState<string | null>(null)

  useEffect(() => {
    if (session) {
      setAmount(String(session.outstanding_shortage))
      setNotes('')
      setAmountError(null)
    }
  }, [session])

  const mutation = useMutation({
    mutationFn: () => recordCashDrawerRecovery(session!.id, { amount: Number(amount), notes: notes || undefined }),
    onSuccess: () => {
      toast.success('Recovery recorded — the ledger now reflects the returned cash')
      queryClient.invalidateQueries({ queryKey: ['cash-drawer-sessions'] })
      onOpenChange(false)
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      const fieldError = info.errors?.amount?.[0]
      if (fieldError) {
        setAmountError(fieldError)
      } else {
        toast.error(info.message)
      }
    },
  })

  return (
    <Dialog open={!!session} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record Recovery</DialogTitle>
        </DialogHeader>

        {session && (
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Outstanding shortage: <span className="font-medium text-foreground">{formatCurrency(session.outstanding_shortage)}</span>
            </p>

            <div className="space-y-1.5">
              <Label>Amount Received</Label>
              <Input
                type="number"
                step="0.01"
                min="0.01"
                max={session.outstanding_shortage}
                value={amount}
                onChange={(event) => {
                  setAmount(event.target.value)
                  setAmountError(null)
                }}
              />
              {amountError && <p className="text-xs text-destructive">{amountError}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>Notes (optional)</Label>
              <Textarea value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="e.g. cashier repaid in cash on 4 Aug" />
            </div>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!amount || Number(amount) <= 0 || mutation.isPending} onClick={() => mutation.mutate()}>
            Record Recovery
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
