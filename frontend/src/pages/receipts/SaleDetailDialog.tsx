import { useQuery } from '@tanstack/react-query'

import { fetchSale } from '@/api/sales'
import { fetchActiveProducts } from '@/api/products'
import { fetchCustomers } from '@/api/customers'
import { fetchUsers } from '@/api/settings'
import { fetchPaymentTypes } from '@/api/pos'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'

interface SaleDetailDialogProps {
  saleId: number | null
  onOpenChange: (open: boolean) => void
}

export function SaleDetailDialog({ saleId, onOpenChange }: SaleDetailDialogProps) {
  const { data: sale, isLoading } = useQuery({ queryKey: ['sale', saleId], queryFn: () => fetchSale(saleId!), enabled: !!saleId })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts(), enabled: !!saleId })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }), enabled: !!saleId })
  const { data: usersPage } = useQuery({ queryKey: ['users-all'], queryFn: () => fetchUsers({ per_page: 100 }), enabled: !!saleId })
  const { data: paymentTypes } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes, enabled: !!saleId })

  const productName = (id: number) => products?.find((p) => p.id === id)?.name ?? `Product #${id}`
  const customerName = (id: number | null) => (id ? customersPage?.data.find((c) => c.id === id)?.name ?? `#${id}` : 'Walk-in Customer')
  const cashierName = (id: number) => usersPage?.data.find((u) => u.id === id)?.name ?? `#${id}`
  const paymentTypeName = (id: number) => paymentTypes?.find((t) => t.id === id)?.name ?? `#${id}`

  return (
    <Dialog open={!!saleId} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{sale ? sale.reference_number : 'Receipt'}</DialogTitle>
        </DialogHeader>

        {isLoading || !sale ? (
          <p className="p-4 text-sm text-muted-foreground">Loading…</p>
        ) : (
          <div className="space-y-4 text-sm">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Date</p>
                <p className="text-foreground">{formatDate(sale.sale_date)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Customer</p>
                <p className="text-foreground">{customerName(sale.customer_id)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Cashier</p>
                <p className="text-foreground">{cashierName(sale.served_by)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
                <p className="capitalize text-foreground">{sale.status}</p>
              </div>
            </div>

            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="py-1.5">Item</th>
                  <th className="py-1.5 text-right">Qty</th>
                  <th className="py-1.5 text-right">Unit Price</th>
                  <th className="py-1.5 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                {sale.items.map((item) => (
                  <tr key={item.id} className="border-b border-border last:border-b-0">
                    <td className="py-1.5 text-foreground">{productName(item.product_id)}</td>
                    <td className="py-1.5 text-right text-foreground">{Number(item.quantity)}</td>
                    <td className="py-1.5 text-right text-foreground">{formatCurrency(item.unit_price)}</td>
                    <td className="py-1.5 text-right font-medium text-foreground">{formatCurrency(item.line_total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="ml-auto w-full max-w-xs space-y-1">
              <div className="flex justify-between text-muted-foreground">
                <span>Subtotal</span>
                <span>{formatCurrency(sale.subtotal)}</span>
              </div>
              {sale.discount_amount > 0 && (
                <div className="flex justify-between text-muted-foreground">
                  <span>Discount</span>
                  <span>-{formatCurrency(sale.discount_amount)}</span>
                </div>
              )}
              {sale.tax_amount > 0 && (
                <div className="flex justify-between text-muted-foreground">
                  <span>Tax</span>
                  <span>{formatCurrency(sale.tax_amount)}</span>
                </div>
              )}
              <div className="flex justify-between border-t border-border pt-1 text-base font-bold text-foreground">
                <span>Total</span>
                <span>{formatCurrency(sale.total_amount)}</span>
              </div>
            </div>

            <div>
              <p className="mb-1.5 text-xs font-semibold uppercase text-muted-foreground">Payments</p>
              {sale.payments.length === 0 ? (
                <p className="text-muted-foreground">No payments recorded</p>
              ) : (
                <div className="space-y-1">
                  {sale.payments.map((payment) => (
                    <div key={payment.id} className="flex justify-between">
                      <span className="text-foreground">{paymentTypeName(payment.payment_type_id)}</span>
                      <span className="text-foreground">{formatCurrency(payment.amount)}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
