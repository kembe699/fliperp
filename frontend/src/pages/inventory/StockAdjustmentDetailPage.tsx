import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2 } from 'lucide-react'

import { approveStockAdjustment, fetchStockAdjustment, fetchWarehouses } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import { STOCK_ADJUSTMENT_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'

export function StockAdjustmentDetailPage() {
  const { id } = useParams<{ id: string }>()
  const adjustmentId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: adjustment, isLoading } = useQuery({ queryKey: ['stock-adjustment', adjustmentId], queryFn: () => fetchStockAdjustment(adjustmentId), enabled: !!adjustmentId })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  const warehouseName = (wid: number) => warehouses?.find((w) => w.id === wid)?.name ?? `#${wid}`
  const productName = (pid: number) => products?.find((p) => p.id === pid)?.name ?? `Product #${pid}`
  const productSku = (pid: number) => products?.find((p) => p.id === pid)?.sku ?? ''

  const approveMutation = useMutation({
    mutationFn: () => approveStockAdjustment(adjustmentId),
    onSuccess: () => {
      toast.success('Stock adjustment approved')
      queryClient.invalidateQueries({ queryKey: ['stock-adjustment', adjustmentId] })
      queryClient.invalidateQueries({ queryKey: ['stock-adjustments'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !adjustment) {
    return <div className="p-6 text-sm text-muted-foreground">Loading stock adjustment…</div>
  }

  const canApprove = can('stock-adjustments.approve') && adjustment.status === 'draft'

  return (
    <div>
      <PageHeader
        parent="Stock Adjustments"
        title={adjustment.reference_number}
        action={
          canApprove && (
            <Button disabled={approveMutation.isPending} onClick={() => approveMutation.mutate()}>
              <CheckCircle2 className="h-4 w-4" />
              Approve
            </Button>
          )
        }
      />

      <Card>
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div className="grid grid-cols-2 gap-6 sm:grid-cols-4">
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Warehouse</p>
                <p className="mt-1 text-sm font-medium text-foreground">{warehouseName(adjustment.warehouse_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Reason</p>
                <p className="mt-1 text-sm text-foreground">{adjustment.reason ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Date</p>
                <p className="mt-1 text-sm text-foreground">{formatDate(adjustment.created_at)}</p>
              </div>
            </div>
            <StatusBadge label={adjustment.status} variant={STOCK_ADJUSTMENT_STATUS_VARIANT[adjustment.status]} className="text-sm" />
          </div>

          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Product</th>
                <th className="py-2">SKU</th>
                <th className="py-2 text-right">System Qty</th>
                <th className="py-2 text-right">Counted Qty</th>
                <th className="py-2 text-right">Variance</th>
              </tr>
            </thead>
            <tbody>
              {adjustment.items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">{productName(item.product_id)}</td>
                  <td className="py-2 text-muted-foreground">{productSku(item.product_id)}</td>
                  <td className="py-2 text-right text-foreground">{item.system_quantity}</td>
                  <td className="py-2 text-right text-foreground">{item.counted_quantity}</td>
                  <td className={`py-2 text-right font-medium ${item.variance < 0 ? 'text-danger' : item.variance > 0 ? 'text-success' : 'text-foreground'}`}>
                    {item.variance > 0 ? '+' : ''}
                    {item.variance}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  )
}
