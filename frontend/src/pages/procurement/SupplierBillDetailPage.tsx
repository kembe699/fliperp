import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, ExternalLink } from 'lucide-react'

import {
  createSupplierPayment,
  fetchGoodsReceivedNote,
  fetchPurchaseOrder,
  fetchSupplierBill,
  fetchSupplierPayments,
  fetchSuppliers,
} from '@/api/procurement'
import { fetchPaymentTypes } from '@/api/pos'
import { fetchUsers } from '@/api/settings'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { usePermissions } from '@/hooks/use-permissions'
import { SUPPLIER_BILL_STATUS_LABEL, SUPPLIER_BILL_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function SupplierBillDetailPage() {
  const { id } = useParams<{ id: string }>()
  const billId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [paymentDialogOpen, setPaymentDialogOpen] = useState(false)
  const [paymentTypeId, setPaymentTypeId] = useState('')
  const [amount, setAmount] = useState('')
  const [amountError, setAmountError] = useState<string | null>(null)
  const [referenceNumber, setReferenceNumber] = useState('')
  const [paymentDate, setPaymentDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [downloading, setDownloading] = useState(false)

  const { data: bill, isLoading } = useQuery({ queryKey: ['supplier-bill', id], queryFn: () => fetchSupplierBill(billId), enabled: !!billId })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: paymentTypes } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes, enabled: paymentDialogOpen })
  const { data: payments, isLoading: paymentsLoading } = useQuery({
    queryKey: ['supplier-payments', billId],
    queryFn: () => fetchSupplierPayments({ supplier_bill_id: billId, per_page: 100 }),
    enabled: !!billId,
  })
  // /users requires users.view, which not every role that can record payments
  // holds — fall back to "User #id" in the payment history table when it's
  // unavailable rather than erroring the whole page.
  const { data: usersPage } = useQuery({
    queryKey: ['users-all'],
    queryFn: () => fetchUsers({ per_page: 100 }),
    enabled: can('users.view'),
  })
  const { data: grn } = useQuery({
    queryKey: ['grn', bill?.grn_id],
    queryFn: () => fetchGoodsReceivedNote(bill!.grn_id!),
    enabled: !!bill?.grn_id,
  })
  const { data: purchaseOrder } = useQuery({
    queryKey: ['purchase-order', bill?.purchase_order_id],
    queryFn: () => fetchPurchaseOrder(bill!.purchase_order_id!),
    enabled: !!bill?.purchase_order_id,
  })

  const supplier = suppliers?.data.find((s) => s.id === bill?.supplier_id)
  const paymentTypeName = (typeId: number | null) => paymentTypes?.find((t) => t.id === typeId)?.name ?? '—'
  const paidByName = (userId: number | null) => {
    if (!userId) return '—'
    return usersPage?.data.find((u) => u.id === userId)?.name ?? `User #${userId}`
  }

  const resetPaymentForm = () => {
    setAmount('')
    setAmountError(null)
    setPaymentTypeId('')
    setReferenceNumber('')
    setPaymentDate(new Date().toISOString().slice(0, 10))
  }

  const openPaymentDialog = () => {
    setAmount(bill ? String(bill.balance_due) : '')
    setPaymentDialogOpen(true)
  }

  const paymentMutation = useMutation({
    mutationFn: () =>
      createSupplierPayment({
        supplier_id: bill!.supplier_id,
        supplier_bill_id: billId,
        payment_date: paymentDate,
        amount: Number(amount),
        payment_type_id: paymentTypeId ? Number(paymentTypeId) : null,
        reference_number: referenceNumber || null,
      }),
    onSuccess: () => {
      toast.success('Payment recorded')
      queryClient.invalidateQueries({ queryKey: ['supplier-bill', id] })
      queryClient.invalidateQueries({ queryKey: ['supplier-bills'] })
      queryClient.invalidateQueries({ queryKey: ['supplier-payments', billId] })
      if (bill) queryClient.invalidateQueries({ queryKey: ['supplier-statement', bill.supplier_id] })
      setPaymentDialogOpen(false)
      resetPaymentForm()
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

  if (isLoading || !bill) {
    return <div className="p-6 text-sm text-muted-foreground">Loading supplier bill…</div>
  }

  const canRecordPayment = can('supplier-payments.create') && bill.balance_due > 0

  const handleDownloadPdf = async () => {
    setDownloading(true)
    try {
      await downloadPdf(`/supplier-bills/${billId}/pdf`, `supplier-bill-${bill.reference_number}.pdf`)
    } catch {
      toast.error('Could not download the supplier bill PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Supplier Bills"
        title={bill.reference_number}
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={downloading} onClick={handleDownloadPdf}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download PDF'}
            </Button>
            {canRecordPayment && <Button onClick={openPaymentDialog}>Record Payment</Button>}
          </div>
        }
      />

      <Card>
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div>
              <p className="text-lg font-bold text-foreground">{supplier?.name ?? `Supplier #${bill.supplier_id}`}</p>
              <p className="text-sm text-muted-foreground">Supplier Bill</p>
            </div>
            <StatusBadge label={SUPPLIER_BILL_STATUS_LABEL[bill.status]} variant={SUPPLIER_BILL_STATUS_VARIANT[bill.status]} className="text-sm" />
          </div>

          <div className="mb-8 grid grid-cols-2 gap-6 sm:grid-cols-4">
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Supplier</p>
              <p className="mt-1 text-sm font-medium text-foreground">{supplier?.name ?? `Supplier #${bill.supplier_id}`}</p>
              <p className="text-sm text-muted-foreground">{supplier?.phone}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Reference</p>
              <p className="mt-1 text-sm text-foreground">{bill.reference_number}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Bill Date</p>
              <p className="mt-1 text-sm text-foreground">{formatDate(bill.bill_date)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Due Date</p>
              <p className="mt-1 text-sm text-foreground">{formatDate(bill.due_date)}</p>
            </div>
          </div>

          {(grn || purchaseOrder) && (
            <div className="mb-8 flex flex-wrap gap-3">
              {grn && (
                <button
                  type="button"
                  onClick={() => navigate(`/goods-received-notes/${grn.id}`)}
                  className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-1.5 text-sm font-medium text-primary hover:bg-accent"
                >
                  <ExternalLink className="h-3.5 w-3.5" />
                  GRN {grn.reference_number}
                </button>
              )}
              {purchaseOrder && (
                <button
                  type="button"
                  onClick={() => navigate(`/purchase-orders/${purchaseOrder.id}`)}
                  className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-1.5 text-sm font-medium text-primary hover:bg-accent"
                >
                  <ExternalLink className="h-3.5 w-3.5" />
                  PO {purchaseOrder.reference_number}
                </button>
              )}
            </div>
          )}

          {/*
            supplier_bills has no line-items table in the schema (subtotal is
            a single stored figure, whether entered manually or rolled up
            from a GRN at creation time) — so unlike Invoices, there's no
            per-line breakdown to render here.
          */}
          <div className="ml-auto w-full max-w-xs space-y-1.5">
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Subtotal</span>
              <span>{formatCurrency(bill.subtotal)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Tax</span>
              <span>{formatCurrency(bill.tax_amount)}</span>
            </div>
            <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold text-foreground">
              <span>Total</span>
              <span>{formatCurrency(bill.total_amount)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Amount Paid</span>
              <span>{formatCurrency(bill.amount_paid)}</span>
            </div>
            <div className="flex justify-between text-sm font-semibold text-danger">
              <span>Balance Due</span>
              <span>{formatCurrency(bill.balance_due)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="mt-6">
        <h2 className="mb-3 text-base font-semibold text-foreground">Payment History</h2>
        <div className="overflow-hidden rounded-xl border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="px-4 py-3">Date</th>
                <th className="px-4 py-3">Amount</th>
                <th className="px-4 py-3">Payment Type</th>
                <th className="px-4 py-3">Reference</th>
                <th className="px-4 py-3">Paid By</th>
              </tr>
            </thead>
            <tbody>
              {paymentsLoading ? (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-sm text-muted-foreground">Loading…</td>
                </tr>
              ) : !payments?.data.length ? (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-sm text-muted-foreground">No payments recorded yet.</td>
                </tr>
              ) : (
                payments.data.map((payment) => (
                  <tr key={payment.id} className="border-b border-border last:border-b-0">
                    <td className="px-4 py-3 text-foreground">{formatDate(payment.payment_date)}</td>
                    <td className="px-4 py-3 font-medium text-foreground">{formatCurrency(payment.amount)}</td>
                    <td className="px-4 py-3 text-foreground">{paymentTypeName(payment.payment_type_id)}</td>
                    <td className="px-4 py-3 text-foreground">{payment.reference_number ?? '—'}</td>
                    <td className="px-4 py-3 text-foreground">{paidByName(payment.paid_by)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <Dialog open={paymentDialogOpen} onOpenChange={(open) => { setPaymentDialogOpen(open); if (!open) resetPaymentForm() }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Record Payment</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Balance due: <span className="font-semibold text-foreground">{formatCurrency(bill.balance_due)}</span>
            </p>
            <div className="space-y-1.5">
              <Label>Amount</Label>
              <Input
                type="number"
                min="0.01"
                step="0.01"
                value={amount}
                onChange={(event) => { setAmount(event.target.value); setAmountError(null) }}
              />
              {amountError && <p className="text-xs text-destructive">{amountError}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Payment Type</Label>
              <Select value={paymentTypeId} onValueChange={setPaymentTypeId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select payment type" />
                </SelectTrigger>
                <SelectContent>
                  {paymentTypes?.map((type) => (
                    <SelectItem key={type.id} value={String(type.id)}>
                      {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Reference Number</Label>
              <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="Optional" />
            </div>
            <div className="space-y-1.5">
              <Label>Payment Date</Label>
              <Input type="date" value={paymentDate} onChange={(event) => setPaymentDate(event.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPaymentDialogOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!amount || paymentMutation.isPending} onClick={() => paymentMutation.mutate()}>
              Record Payment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
