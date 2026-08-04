import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Ban, CheckCircle2, Truck } from 'lucide-react'

import { cancelStockTransfer, completeStockTransfer, fetchStockTransfer, fetchWarehouses, markStockTransferInTransit } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import { STOCK_TRANSFER_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { StockTransferStatus } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'

const TIMELINE: StockTransferStatus[] = ['pending', 'in_transit', 'completed']

export function StockTransferDetailPage() {
  const { id } = useParams<{ id: string }>()
  const transferId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: transfer, isLoading } = useQuery({ queryKey: ['stock-transfer', transferId], queryFn: () => fetchStockTransfer(transferId), enabled: !!transferId })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  const warehouseName = (wid: number) => warehouses?.find((w) => w.id === wid)?.name ?? `#${wid}`
  const productName = (pid: number) => products?.find((p) => p.id === pid)?.name ?? `Product #${pid}`
  const productSku = (pid: number) => products?.find((p) => p.id === pid)?.sku ?? ''

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['stock-transfer', transferId] })
    queryClient.invalidateQueries({ queryKey: ['stock-transfers'] })
  }

  const inTransitMutation = useMutation({
    mutationFn: () => markStockTransferInTransit(transferId),
    onSuccess: () => {
      toast.success('Transfer marked as in transit')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const completeMutation = useMutation({
    mutationFn: () => completeStockTransfer(transferId),
    onSuccess: () => {
      toast.success('Transfer completed')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelStockTransfer(transferId),
    onSuccess: () => {
      toast.success('Transfer cancelled')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !transfer) {
    return <div className="p-6 text-sm text-muted-foreground">Loading stock transfer…</div>
  }

  const canMarkInTransit = can('stock-transfers.update') && transfer.status === 'pending'
  const canComplete = can('stock-transfers.complete') && transfer.status === 'in_transit'
  const canCancel = can('stock-transfers.cancel') && ['pending', 'in_transit'].includes(transfer.status)
  const timelineIndex = TIMELINE.indexOf(transfer.status)

  return (
    <div>
      <PageHeader
        parent="Stock Transfers"
        title={transfer.reference_number}
        action={
          <div className="flex gap-2">
            {canMarkInTransit && (
              <Button variant="outline" disabled={inTransitMutation.isPending} onClick={() => inTransitMutation.mutate()}>
                <Truck className="h-4 w-4" />
                Mark In Transit
              </Button>
            )}
            {canComplete && (
              <Button disabled={completeMutation.isPending} onClick={() => completeMutation.mutate()}>
                <CheckCircle2 className="h-4 w-4" />
                Complete
              </Button>
            )}
            {canCancel && (
              <Button variant="outline" className="text-destructive" disabled={cancelMutation.isPending} onClick={() => cancelMutation.mutate()}>
                <Ban className="h-4 w-4" />
                Cancel
              </Button>
            )}
          </div>
        }
      />

      <Card className="mb-6">
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div className="grid grid-cols-2 gap-6 sm:grid-cols-4">
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">From</p>
                <p className="mt-1 text-sm font-medium text-foreground">{warehouseName(transfer.from_warehouse_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">To</p>
                <p className="mt-1 text-sm font-medium text-foreground">{warehouseName(transfer.to_warehouse_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Date</p>
                <p className="mt-1 text-sm text-foreground">{formatDate(transfer.created_at)}</p>
              </div>
            </div>
            <StatusBadge label={transfer.status.replace('_', ' ')} variant={STOCK_TRANSFER_STATUS_VARIANT[transfer.status]} className="text-sm" />
          </div>

          {transfer.status === 'cancelled' ? (
            <p className="mb-8 text-sm text-danger">This transfer was cancelled.</p>
          ) : (
            <div className="mb-8 flex items-center">
              {TIMELINE.map((step, index) => (
                <div key={step} className="flex flex-1 items-center last:flex-none">
                  <div className="flex flex-col items-center gap-1.5">
                    <div
                      className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                        index <= timelineIndex ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                      }`}
                    >
                      {index + 1}
                    </div>
                    <span className="text-xs capitalize text-muted-foreground">{step.replace('_', ' ')}</span>
                  </div>
                  {index < TIMELINE.length - 1 && <div className={`mx-2 h-0.5 flex-1 ${index < timelineIndex ? 'bg-primary' : 'bg-muted'}`} />}
                </div>
              ))}
            </div>
          )}

          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Product</th>
                <th className="py-2">SKU</th>
                <th className="py-2 text-right">Quantity</th>
              </tr>
            </thead>
            <tbody>
              {transfer.items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">{productName(item.product_id)}</td>
                  <td className="py-2 text-muted-foreground">{productSku(item.product_id)}</td>
                  <td className="py-2 text-right text-foreground">{item.quantity}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  )
}
