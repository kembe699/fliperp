import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Ban, CheckCircle2, Download, Mail, PackageCheck } from 'lucide-react'

import { approvePurchaseOrder, cancelPurchaseOrder, emailPurchaseOrder, fetchPurchaseOrder, fetchReceivingStatus, fetchSuppliers } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { usePermissions } from '@/hooks/use-permissions'
import { PURCHASE_ORDER_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'

export function PurchaseOrderDetailPage() {
  const { id } = useParams<{ id: string }>()
  const poId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [downloading, setDownloading] = useState(false)

  const { data: po, isLoading } = useQuery({ queryKey: ['purchase-order', poId], queryFn: () => fetchPurchaseOrder(poId), enabled: !!poId })
  const { data: receivingStatus } = useQuery({
    queryKey: ['po-receiving-status', poId],
    queryFn: () => fetchReceivingStatus(poId),
    enabled: !!poId && po ? ['approved', 'partially_received', 'received'].includes(po.status) : false,
  })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  const supplierName = (sid: number) => suppliers?.data.find((s) => s.id === sid)?.name ?? `Supplier #${sid}`
  const warehouseName = (wid: number) => warehouses?.find((w) => w.id === wid)?.name ?? `#${wid}`
  const productName = (pid: number) => products?.find((p) => p.id === pid)?.name ?? `Product #${pid}`
  const productSku = (pid: number) => products?.find((p) => p.id === pid)?.sku ?? ''

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['purchase-order', poId] })
    queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
  }

  const approveMutation = useMutation({
    mutationFn: () => approvePurchaseOrder(poId),
    onSuccess: () => {
      toast.success('Purchase order approved')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelPurchaseOrder(poId),
    onSuccess: () => {
      toast.success('Purchase order cancelled')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const emailMutation = useMutation({
    mutationFn: () => emailPurchaseOrder(poId),
    onSuccess: (message) => toast.success(message),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownloadPdf = async () => {
    if (!po) return
    setDownloading(true)
    try {
      await downloadPdf(`/purchase-orders/${poId}/pdf`, `purchase-order-${po.reference_number}.pdf`)
    } catch {
      toast.error('Could not download the purchase order PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  if (isLoading || !po) {
    return <div className="p-6 text-sm text-muted-foreground">Loading purchase order…</div>
  }

  const total = po.items.reduce((sum, item) => sum + Number(item.quantity_ordered) * Number(item.unit_cost), 0)
  const canApprove = can('purchase-orders.approve') && po.status === 'submitted'
  const canCancel = can('purchase-orders.cancel') && ['draft', 'submitted', 'approved'].includes(po.status)
  const canReceive = can('goods-received-notes.create') && ['approved', 'partially_received'].includes(po.status)

  return (
    <div>
      <PageHeader
        parent="Purchase Orders"
        title={po.reference_number}
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={downloading} onClick={handleDownloadPdf}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download PDF'}
            </Button>
            {can('purchase-orders.view') && (
              <Button variant="outline" disabled={emailMutation.isPending} onClick={() => emailMutation.mutate()}>
                <Mail className="h-4 w-4" />
                {emailMutation.isPending ? 'Sending…' : 'Email Supplier'}
              </Button>
            )}
            {canApprove && (
              <Button variant="outline" disabled={approveMutation.isPending} onClick={() => approveMutation.mutate()}>
                <CheckCircle2 className="h-4 w-4" />
                Approve
              </Button>
            )}
            {canReceive && (
              <Button onClick={() => navigate(`/goods-received-notes/new?purchase_order_id=${po.id}`)}>
                <PackageCheck className="h-4 w-4" />
                Receive Goods
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
                <p className="text-xs font-semibold uppercase text-muted-foreground">Supplier</p>
                <p className="mt-1 text-sm font-medium text-foreground">{supplierName(po.supplier_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Warehouse</p>
                <p className="mt-1 text-sm text-foreground">{warehouseName(po.warehouse_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Order Date</p>
                <p className="mt-1 text-sm text-foreground">{formatDate(po.order_date)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Expected Delivery</p>
                <p className="mt-1 text-sm text-foreground">{po.expected_delivery_date ? formatDate(po.expected_delivery_date) : '—'}</p>
              </div>
            </div>
            <StatusBadge label={po.status.replace(/_/g, ' ')} variant={PURCHASE_ORDER_STATUS_VARIANT[po.status]} className="text-sm" />
          </div>

          <table className="mb-6 w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Product</th>
                <th className="py-2 text-right">Qty Ordered</th>
                <th className="py-2 text-right">Qty Received</th>
                <th className="py-2 text-right">Unit Cost</th>
                <th className="py-2 text-right">Line Total</th>
              </tr>
            </thead>
            <tbody>
              {po.items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">
                    {productName(item.product_id)}
                    <span className="ml-1 text-xs text-muted-foreground">{productSku(item.product_id)}</span>
                  </td>
                  <td className="py-2 text-right text-foreground">{item.quantity_ordered}</td>
                  <td className="py-2 text-right text-foreground">{item.quantity_received}</td>
                  <td className="py-2 text-right text-foreground">{formatCurrency(item.unit_cost)}</td>
                  <td className="py-2 text-right font-medium text-foreground">{formatCurrency(Number(item.quantity_ordered) * Number(item.unit_cost))}</td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="ml-auto w-full max-w-xs border-t border-border pt-1.5">
            <div className="flex justify-between text-base font-bold text-foreground">
              <span>Total</span>
              <span>{formatCurrency(total)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {receivingStatus && (
        <Card>
          <CardContent className="p-6">
            <p className="mb-3 text-sm font-semibold text-foreground">Receiving Status</p>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="py-2">Product</th>
                  <th className="py-2 text-right">Ordered</th>
                  <th className="py-2 text-right">Received</th>
                  <th className="py-2 text-right">Outstanding</th>
                  <th className="py-2 text-right">Status</th>
                </tr>
              </thead>
              <tbody>
                {receivingStatus.items.map((item) => (
                  <tr key={item.purchase_order_item_id} className="border-b border-border last:border-b-0">
                    <td className="py-2 text-foreground">{productName(item.product_id)}</td>
                    <td className="py-2 text-right text-foreground">{item.quantity_ordered}</td>
                    <td className="py-2 text-right text-foreground">{item.quantity_received}</td>
                    <td className="py-2 text-right text-foreground">{item.quantity_outstanding}</td>
                    <td className="py-2 text-right">
                      <StatusBadge label={item.fully_received ? 'Fully Received' : 'Outstanding'} variant={item.fully_received ? 'success' : 'warning'} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
