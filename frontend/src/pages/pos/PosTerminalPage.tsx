import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { PauseCircle } from 'lucide-react'

import { fetchCurrentCashDrawerSession, fetchPaymentTypes, fetchTaxRates, fetchWarehouses } from '@/api/pos'
import { fetchActiveProducts } from '@/api/products'
import { fetchCustomers } from '@/api/customers'
import { addSalePayment, completeSale, createSale, fetchHeldSales, updateSale } from '@/api/sales'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { printPdf } from '@/lib/pdf-print'
import type { Sale } from '@/types/sale'
import { type CartLine, cartTotal } from '@/pages/pos/types'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { ProductGrid } from '@/pages/pos/ProductGrid'
import { CartPanel } from '@/pages/pos/CartPanel'
import { PaymentDialog } from '@/pages/pos/PaymentDialog'
import { HeldSalesDialog } from '@/pages/pos/HeldSalesDialog'
import { OpenDrawerDialog } from '@/pages/pos/OpenDrawerDialog'

export function PosTerminalPage() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)

  const { data: drawerSession, isLoading: drawerLoading } = useQuery({
    queryKey: ['cash-drawer-current'],
    queryFn: fetchCurrentCashDrawerSession,
  })

  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: paymentTypes } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes })
  const { data: taxRates } = useQuery({ queryKey: ['tax-rates'], queryFn: fetchTaxRates })
  const { data: products } = useQuery({ queryKey: ['pos-products'], queryFn: () => fetchActiveProducts() })
  const { data: customersPage } = useQuery({
    queryKey: ['pos-customers'],
    queryFn: () => fetchCustomers({ per_page: 100, is_active: true }),
  })
  const customers = customersPage?.data ?? []

  const warehouseId = useMemo(() => {
    if (!warehouses || warehouses.length === 0) return null
    return warehouses.find((warehouse) => warehouse.is_default)?.id ?? warehouses[0].id
  }, [warehouses])

  const [cart, setCart] = useState<CartLine[]>([])
  const [customerId, setCustomerId] = useState<number | null>(null)
  const [activeSaleId, setActiveSaleId] = useState<number | null>(null)
  const [resumingReference, setResumingReference] = useState<string | null>(null)
  const [paymentDialogOpen, setPaymentDialogOpen] = useState(false)
  const [heldSalesOpen, setHeldSalesOpen] = useState(false)

  const { data: heldSales } = useQuery({ queryKey: ['held-sales'], queryFn: fetchHeldSales })

  const resetCart = () => {
    setCart([])
    setCustomerId(null)
    setActiveSaleId(null)
    setResumingReference(null)
  }

  const handleAddProduct = (productId: number) => {
    const product = products?.find((p) => p.id === productId)
    if (!product) return

    setCart((prev) => {
      const existing = prev.find((line) => line.productId === productId)
      if (existing) {
        return prev.map((line) => (line.productId === productId ? { ...line, quantity: line.quantity + 1 } : line))
      }
      const taxRate = taxRates?.find((rate) => rate.id === product.tax_rate_id)
      return [
        ...prev,
        {
          productId: product.id,
          productName: product.name,
          sku: product.sku,
          unitPrice: Number(product.selling_price),
          quantity: 1,
          taxRateId: product.tax_rate_id,
          taxRatePercent: taxRate ? Number(taxRate.rate) : 0,
        },
      ]
    })
  }

  const handleQuantityChange = (productId: number, quantity: number) => {
    setCart((prev) => prev.map((line) => (line.productId === productId ? { ...line, quantity } : line)))
  }

  const handleRemove = (productId: number) => {
    setCart((prev) => prev.filter((line) => line.productId !== productId))
  }

  const persistMutation = useMutation({
    mutationFn: async (): Promise<Sale> => {
      const items = cart.map((line) => ({
        product_id: line.productId,
        quantity: line.quantity,
        unit_price: line.unitPrice,
        tax_rate_id: line.taxRateId,
      }))

      if (activeSaleId) {
        return updateSale(activeSaleId, { customer_id: customerId, items })
      }

      if (!warehouseId) {
        throw new Error('No warehouse is configured for this branch yet.')
      }

      return createSale({
        branch_id: user?.branch_id ?? undefined,
        warehouse_id: warehouseId,
        customer_id: customerId,
        sale_type: 'pos',
        items,
      })
    },
  })

  const handleHold = async () => {
    try {
      await persistMutation.mutateAsync()
      toast.success('Sale held')
      resetCart()
      queryClient.invalidateQueries({ queryKey: ['held-sales'] })
    } catch (error) {
      toast.error(getApiErrorInfo(error).message)
    }
  }

  const handlePayClick = async () => {
    try {
      const sale = await persistMutation.mutateAsync()
      setActiveSaleId(sale.id)
      setPaymentDialogOpen(true)
    } catch (error) {
      toast.error(getApiErrorInfo(error).message)
    }
  }

  const completeMutation = useMutation({
    mutationFn: async (rows: { paymentTypeId: number; amount: number }[]) => {
      if (!activeSaleId) throw new Error('No active sale')
      for (const row of rows) {
        await addSalePayment(activeSaleId, { payment_type_id: row.paymentTypeId, amount: row.amount })
      }
      return completeSale(activeSaleId)
    },
    onSuccess: (sale) => {
      toast.success('Sale completed', {
        action: { label: 'Print Receipt', onClick: () => printPdf(`/sales/${sale.id}/receipt`) },
      })
      printPdf(`/sales/${sale.id}/receipt`).catch(() => {
        toast.error('Could not auto-print the receipt. Use "Print Receipt" to try again.')
      })
      setPaymentDialogOpen(false)
      resetCart()
      queryClient.invalidateQueries({ queryKey: ['held-sales'] })
      queryClient.invalidateQueries({ queryKey: ['pos-stock-levels'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleResume = (sale: Sale) => {
    const lines: CartLine[] = sale.items.map((item) => {
      const product = products?.find((p) => p.id === item.product_id)
      const taxRate = taxRates?.find((rate) => rate.id === item.tax_rate_id)
      return {
        productId: item.product_id,
        productName: product?.name ?? `Product #${item.product_id}`,
        sku: product?.sku ?? '',
        unitPrice: Number(item.unit_price),
        quantity: Number(item.quantity),
        taxRateId: item.tax_rate_id,
        taxRatePercent: taxRate ? Number(taxRate.rate) : 0,
      }
    })
    setCart(lines)
    setCustomerId(sale.customer_id)
    setActiveSaleId(sale.id)
    setResumingReference(sale.reference_number)
    setHeldSalesOpen(false)
  }

  const selectedCustomer = customers.find((customer) => customer.id === customerId)
  const allowPartial = selectedCustomer?.customer_type === 'credit'

  if (drawerLoading) {
    return <div className="p-6 text-sm text-muted-foreground">Loading terminal…</div>
  }

  if (!drawerSession) {
    return <OpenDrawerDialog branchId={user?.branch_id ?? undefined} />
  }

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col">
      <PageHeader
        parent="Sales"
        title="POS Terminal"
        action={
          <Button variant="outline" onClick={() => setHeldSalesOpen(true)} className="gap-2">
            <PauseCircle className="h-4 w-4" />
            Held Sales
            {heldSales && heldSales.length > 0 && (
              <span className="ml-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs text-primary-foreground">
                {heldSales.length}
              </span>
            )}
          </Button>
        }
      />

      <div className="grid min-h-0 flex-1 grid-cols-1 gap-6 lg:grid-cols-[1fr_380px]">
        <div className="min-h-0 rounded-xl border border-border bg-card p-4">
          <ProductGrid warehouseId={warehouseId} onAddProduct={handleAddProduct} />
        </div>
        <div className="min-h-0">
          <CartPanel
            lines={cart}
            onQuantityChange={handleQuantityChange}
            onRemove={handleRemove}
            customers={customers}
            customerId={customerId}
            onCustomerChange={setCustomerId}
            onHold={handleHold}
            onPay={handlePayClick}
            isBusy={persistMutation.isPending}
            resumingReference={resumingReference}
          />
        </div>
      </div>

      <PaymentDialog
        open={paymentDialogOpen}
        onOpenChange={setPaymentDialogOpen}
        totalDue={cartTotal(cart)}
        paymentTypes={paymentTypes ?? []}
        allowPartial={allowPartial}
        isSubmitting={completeMutation.isPending}
        onComplete={(rows) => completeMutation.mutate(rows)}
      />

      <HeldSalesDialog open={heldSalesOpen} onOpenChange={setHeldSalesOpen} onResume={handleResume} />
    </div>
  )
}
