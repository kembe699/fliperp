import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { cancelHeldSale, fetchHeldSales } from '@/api/sales'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import type { Sale } from '@/types/sale'

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/shared/EmptyState'
import { Skeleton } from '@/components/ui/skeleton'

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

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Held Sales</DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <div className="space-y-2">
            {Array.from({ length: 3 }).map((_, index) => (
              <Skeleton key={index} className="h-16 w-full rounded-lg" />
            ))}
          </div>
        ) : !heldSales || heldSales.length === 0 ? (
          <EmptyState title="No held sales" subtext="Parked carts from this cash drawer session will show up here." />
        ) : (
          <ul className="max-h-96 space-y-2 overflow-y-auto">
            {heldSales.map((sale) => (
              <li key={sale.id} className="flex items-center justify-between rounded-lg border border-border p-3">
                <div>
                  <p className="text-sm font-medium text-foreground">{sale.reference_number}</p>
                  <p className="text-xs text-muted-foreground">
                    {sale.items.length} item{sale.items.length === 1 ? '' : 's'} · {formatDate(sale.sale_date)}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="mr-1 text-sm font-semibold text-foreground">{formatCurrency(sale.total_amount)}</span>
                  {confirmId === sale.id ? (
                    <>
                      <Button size="sm" variant="destructive" disabled={cancelMutation.isPending} onClick={() => cancelMutation.mutate(sale)}>
                        {cancelMutation.isPending ? 'Cancelling…' : 'Yes, cancel it'}
                      </Button>
                      <Button size="sm" variant="ghost" onClick={() => setConfirmId(null)}>
                        Keep
                      </Button>
                    </>
                  ) : (
                    <>
                      <Button size="sm" variant="outline" className="text-destructive hover:text-destructive" onClick={() => setConfirmId(sale.id)}>
                        Cancel
                      </Button>
                      <Button size="sm" onClick={() => onResume(sale)}>
                        Resume
                      </Button>
                    </>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  )
}
