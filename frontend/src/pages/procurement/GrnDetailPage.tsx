import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2, Download } from 'lucide-react'

import { confirmGoodsReceivedNote, fetchGoodsReceivedNote, fetchSuppliers, findSupplierBillByGrnId } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { usePermissions } from '@/hooks/use-permissions'
import { GRN_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { GrnItemCondition } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'

const CONDITION_VARIANT: Record<GrnItemCondition, 'success' | 'warning' | 'danger'> = {
  good: 'success',
  damaged: 'warning',
  rejected: 'danger',
}

export function GrnDetailPage() {
  const { id } = useParams<{ id: string }>()
  const grnId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [confirmedThisSession, setConfirmedThisSession] = useState(false)
  const [downloading, setDownloading] = useState(false)

  const { data: grn, isLoading } = useQuery({ queryKey: ['grn', grnId], queryFn: () => fetchGoodsReceivedNote(grnId), enabled: !!grnId })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })
  const { data: linkedBill } = useQuery({
    queryKey: ['grn-bill', grnId],
    queryFn: () => findSupplierBillByGrnId(grnId),
    enabled: !!grnId && grn?.status === 'confirmed',
  })

  const supplierName = (sid: number) => suppliers?.data.find((s) => s.id === sid)?.name ?? `Supplier #${sid}`
  const warehouseName = (wid: number) => warehouses?.find((w) => w.id === wid)?.name ?? `#${wid}`
  const productName = (pid: number) => products?.find((p) => p.id === pid)?.name ?? `Product #${pid}`

  const confirmMutation = useMutation({
    mutationFn: () => confirmGoodsReceivedNote(grnId),
    onSuccess: () => {
      toast.success('Goods received note confirmed')
      setConfirmedThisSession(true)
      queryClient.invalidateQueries({ queryKey: ['grn', grnId] })
      queryClient.invalidateQueries({ queryKey: ['goods-received-notes'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !grn) {
    return <div className="p-6 text-sm text-muted-foreground">Loading goods received note…</div>
  }

  const canConfirm = can('goods-received-notes.confirm') && grn.status === 'draft'
  const total = grn.items.reduce((sum, item) => sum + Number(item.quantity_received) * Number(item.unit_cost), 0)

  const handleDownloadPdf = async () => {
    setDownloading(true)
    try {
      await downloadPdf(`/goods-received-notes/${grnId}/pdf`, `grn-${grn.reference_number}.pdf`)
    } catch {
      toast.error('Could not download the GRN PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Goods Received Notes"
        title={grn.reference_number}
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={downloading} onClick={handleDownloadPdf}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download PDF'}
            </Button>
            {canConfirm && (
              <Button disabled={confirmMutation.isPending} onClick={() => confirmMutation.mutate()}>
                <CheckCircle2 className="h-4 w-4" />
                Confirm
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
                <p className="mt-1 text-sm font-medium text-foreground">{supplierName(grn.supplier_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Warehouse</p>
                <p className="mt-1 text-sm text-foreground">{warehouseName(grn.warehouse_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Received Date</p>
                <p className="mt-1 text-sm text-foreground">{formatDate(grn.received_date)}</p>
              </div>
              {grn.purchase_order_id && (
                <div>
                  <p className="text-xs font-semibold uppercase text-muted-foreground">Purchase Order</p>
                  <p className="mt-1 text-sm text-foreground">
                    <a href={`/purchase-orders/${grn.purchase_order_id}`} className="text-primary hover:underline">
                      PO #{grn.purchase_order_id}
                    </a>
                  </p>
                </div>
              )}
            </div>
            <StatusBadge label={grn.status} variant={GRN_STATUS_VARIANT[grn.status]} className="text-sm" />
          </div>

          <table className="mb-6 w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Product</th>
                <th className="py-2 text-right">Qty Received</th>
                <th className="py-2 text-right">Unit Cost</th>
                <th className="py-2 text-right">Line Total</th>
                <th className="py-2 text-right">Condition</th>
              </tr>
            </thead>
            <tbody>
              {grn.items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">{productName(item.product_id)}</td>
                  <td className="py-2 text-right text-foreground">{item.quantity_received}</td>
                  <td className="py-2 text-right text-foreground">{formatCurrency(item.unit_cost)}</td>
                  <td className="py-2 text-right font-medium text-foreground">{formatCurrency(Number(item.quantity_received) * Number(item.unit_cost))}</td>
                  <td className="py-2 text-right">
                    <StatusBadge label={item.condition} variant={CONDITION_VARIANT[item.condition]} />
                  </td>
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

          {grn.status === 'confirmed' && (
            <p className="mt-4 text-sm text-muted-foreground">
              {linkedBill ? (
                <>
                  A supplier bill was created for this receipt:{' '}
                  <button type="button" onClick={() => navigate(`/supplier-bills/${linkedBill.id}`)} className="font-medium text-primary hover:underline">
                    {linkedBill.reference_number}
                  </button>{' '}
                  ({formatCurrency(linkedBill.total_amount)}).
                </>
              ) : confirmedThisSession ? (
                'This receipt was just confirmed. If it was linked to a purchase order, a supplier bill should follow shortly.'
              ) : (
                'No linked supplier bill was found for this receipt.'
              )}
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
