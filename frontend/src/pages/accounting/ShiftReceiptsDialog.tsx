import { useQuery } from '@tanstack/react-query'

import { fetchSales } from '@/api/sales'
import { formatCurrency } from '@/lib/format'
import { SALE_STATUS_LABEL, SALE_STATUS_VARIANT } from '@/components/sales/status-variants'

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { StatusBadge } from '@/components/shared/StatusBadge'

interface ShiftReceiptsDialogProps {
  sessionId: number | null
  onOpenChange: (open: boolean) => void
}

export function ShiftReceiptsDialog({ sessionId, onOpenChange }: ShiftReceiptsDialogProps) {
  const { data, isLoading } = useQuery({
    queryKey: ['shift-receipts', sessionId],
    queryFn: () => fetchSales({ cash_drawer_session_id: sessionId!, per_page: 200 }),
    enabled: !!sessionId,
  })

  const sales = data?.data ?? []
  const flagged = sales.filter((sale) => sale.status === 'voided' || sale.status === 'refunded')

  return (
    <Dialog open={!!sessionId} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Shift Receipts</DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <p className="p-4 text-sm text-muted-foreground">Loading…</p>
        ) : sales.length === 0 ? (
          <p className="p-4 text-sm text-muted-foreground">No sales were rung up during this shift.</p>
        ) : (
          <div className="space-y-3">
            {flagged.length > 0 && (
              <p className="text-sm font-medium text-danger">
                {flagged.length} {flagged.length === 1 ? 'sale was' : 'sales were'} voided or refunded during this shift — flagged below.
              </p>
            )}
            <div className="max-h-96 overflow-y-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-card">
                  <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                    <th className="px-3 py-2">Reference</th>
                    <th className="px-3 py-2">Time</th>
                    <th className="px-3 py-2 text-right">Total</th>
                    <th className="px-3 py-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {sales.map((sale) => (
                    <tr key={sale.id} className="border-b border-border last:border-b-0">
                      <td className="px-3 py-2 text-foreground">{sale.reference_number}</td>
                      <td className="px-3 py-2 text-muted-foreground">{new Date(sale.created_at).toLocaleTimeString()}</td>
                      <td className="px-3 py-2 text-right text-foreground">{formatCurrency(sale.total_amount)}</td>
                      <td className="px-3 py-2">
                        <StatusBadge label={SALE_STATUS_LABEL[sale.status]} variant={SALE_STATUS_VARIANT[sale.status]} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
