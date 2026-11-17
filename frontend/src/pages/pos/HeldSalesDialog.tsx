import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Clock, Play, ShoppingBag, X } from 'lucide-react'

import { cancelHeldSale, fetchHeldSales } from '@/api/sales'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import type { Sale } from '@/types/sale'

import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/shared/EmptyState'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

interface HeldSalesDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onResume: (sale: Sale) => void
  /** Called after a held sale is cancelled (the terminal clears its cart if it was that sale). */
  onCancelled?: (sale: Sale) => void
}

export function HeldSalesDialog({ open, onOpenChange, onResume, onCancelled }: HeldSalesDialogProps) {
  const queryClient = useQueryClient()
  const { data: heldSales, isLoading } = useQuery({ queryKey: ['held-sales'], queryFn: fetchHeldSales, enabled: open })
  const [confirmId, setConfirmId] = useState<number | null>(null)

  const cancelMutation = useMutation({
    mutationFn: (sale: Sale) => cancelHeldSale(sale.id),
    onSuccess: (sale) => {
      toast.success(`Held sale ${sale.reference_number} cancelled`)
      setConfirmId(null)
      queryClient.invalidateQueries({ queryKey: ['held-sales'] })
      onCancelled?.(sale)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const heldAt = (sale: Sale) =>
    new Date(sale.created_at).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
  const units = (sale: Sale) => sale.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0)

  return (
    <Dialog open={open} onOpenChange={(next) => { if (!next) setConfirmId(null); onOpenChange(next) }}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>Held Sales</DialogTitle>
          <DialogDescription>Carts parked from this cash drawer session. Resume one to finish it.</DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <FlipLoaderBlock className="min-h-[200px]" label="Loading held sales…" />
        ) : !heldSales || heldSales.length === 0 ? (
          <EmptyState title="No held sales" subtext="Parked carts from this cash drawer session will show up here." />
        ) : (
          <ul className="-mx-1 max-h-[60vh] space-y-3 overflow-y-auto px-1 py-1">
            {heldSales.map((sale) => {
              const confirming = confirmId === sale.id
              return (
                <li key={sale.id} className={`rounded-xl border p-4 transition-colors ${confirming ? 'border-destructive/40 bg-danger-bg/40' : 'border-border hover:border-primary/40'}`}>
                  <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-semibold text-foreground" title={sale.reference_number}>
                        {sale.reference_number}
                      </p>
                      <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                          <ShoppingBag className="h-3.5 w-3.5" />
                          {sale.items.length} item{sale.items.length === 1 ? '' : 's'}
                          {units(sale) !== sale.items.length && ` · ${units(sale)} units`}
                        </span>
                        <span className="inline-flex items-center gap-1">
                          <Clock className="h-3.5 w-3.5" />
                          Held {heldAt(sale)}
                        </span>
                      </p>
                    </div>
                    <p className="shrink-0 text-lg font-bold tabular-nums text-foreground">{formatCurrency(sale.total_amount)}</p>
                  </div>

                  {confirming ? (
                    <div className="mt-4 flex flex-col gap-3 border-t border-destructive/20 pt-4 sm:flex-row sm:items-center sm:justify-between">
                      <p className="text-sm text-destructive">Cancel this held sale? It can’t be resumed afterwards.</p>
                      <div className="flex gap-2 sm:shrink-0">
                        <Button size="sm" variant="outline" className="flex-1 sm:flex-none" onClick={() => setConfirmId(null)}>
                          Keep it
                        </Button>
                        <Button size="sm" variant="destructive" className="flex-1 sm:flex-none" disabled={cancelMutation.isPending} onClick={() => cancelMutation.mutate(sale)}>
                          {cancelMutation.isPending ? 'Cancelling…' : 'Yes, cancel'}
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="mt-4 flex gap-2 border-t border-border pt-4 sm:justify-end">
                      <Button size="sm" variant="outline" className="flex-1 text-destructive hover:text-destructive sm:flex-none" onClick={() => setConfirmId(sale.id)}>
                        <X className="h-4 w-4" />
                        Cancel sale
                      </Button>
                      <Button size="sm" className="flex-1 sm:flex-none" onClick={() => onResume(sale)}>
                        <Play className="h-4 w-4" />
                        Resume
                      </Button>
                    </div>
                  )}
                </li>
              )
            })}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  )
}
