import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, Pencil, Send, XCircle } from 'lucide-react'

import { cancelInvoice, fetchInvoice, sendInvoice } from '@/api/invoices'
import { fetchCustomers } from '@/api/customers'
import { fetchActiveProducts } from '@/api/products'
import { fetchPaymentTypes } from '@/api/pos'
import { createCustomerPayment } from '@/api/customerPayments'
import { formatCurrency, formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import { INVOICE_STATUS_LABEL, INVOICE_STATUS_VARIANT } from '@/components/sales/status-variants'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function InvoiceDetailPage() {
  const { id } = useParams<{ id: string }>()
  const invoiceId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const company = useAuthStore((state) => state.company)
  const { can } = usePermissions()

  const [paymentDialogOpen, setPaymentDialogOpen] = useState(false)
  const [paymentTypeId, setPaymentTypeId] = useState('')
  const [amount, setAmount] = useState('')
  const [paymentDate, setPaymentDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [downloading, setDownloading] = useState(false)

  const { data: invoice, isLoading } = useQuery({ queryKey: ['invoice', id], queryFn: () => fetchInvoice(invoiceId), enabled: !!invoiceId })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })
  const { data: paymentTypes } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes, enabled: paymentDialogOpen })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  const customer = customersPage?.data.find((c) => c.id === invoice?.customer_id)
  const productName = (productId: number) => products?.find((product) => product.id === productId)?.name ?? `Product #${productId}`

  const sendMutation = useMutation({
    mutationFn: () => sendInvoice(invoiceId),
    onSuccess: () => {
      toast.success('Invoice sent')
      queryClient.invalidateQueries({ queryKey: ['invoice', id] })
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelInvoice(invoiceId),
    onSuccess: () => {
      toast.success('Invoice cancelled')
      queryClient.invalidateQueries({ queryKey: ['invoice', id] })
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const paymentMutation = useMutation({
    mutationFn: () =>
      createCustomerPayment({
        customer_id: invoice!.customer_id,
        invoice_id: invoiceId,
        payment_type_id: Number(paymentTypeId),
        payment_date: paymentDate,
        amount: Number(amount),
      }),
    onSuccess: () => {
      toast.success('Payment recorded')
      queryClient.invalidateQueries({ queryKey: ['invoice', id] })
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
      setPaymentDialogOpen(false)
      setAmount('')
      setPaymentTypeId('')
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownloadPdf = async () => {
    if (!invoice) return
    setDownloading(true)
    try {
      await downloadPdf(`/invoices/${invoiceId}/pdf`, `invoice-${invoice.reference_number}.pdf`)
    } catch {
      toast.error('Could not download the invoice PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  if (isLoading || !invoice) {
    return <div className="p-6 text-sm text-muted-foreground">Loading invoice…</div>
  }

  const canRecordPayment = can('customer-payments.create') && ['sent', 'partially_paid', 'overdue'].includes(invoice.status)
  const canCancel = can('invoices.cancel') && ['draft', 'sent'].includes(invoice.status) && Number(invoice.amount_paid) === 0
  const canSend = can('invoices.send') && invoice.status === 'draft'
  const canEdit = can('invoices.update') && invoice.status === 'draft'

  return (
    <div>
      <PageHeader
        parent="Invoices"
        title={invoice.reference_number}
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={downloading} onClick={handleDownloadPdf}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download PDF'}
            </Button>
            {canEdit && (
              <Button variant="outline" onClick={() => navigate(`/invoices/${invoiceId}/edit`)}>
                <Pencil className="h-4 w-4" />
                Edit
              </Button>
            )}
            {canSend && (
              <Button variant="outline" disabled={sendMutation.isPending} onClick={() => sendMutation.mutate()}>
                <Send className="h-4 w-4" />
                Send
              </Button>
            )}
            {canCancel && (
              <Button variant="outline" className="text-destructive" disabled={cancelMutation.isPending} onClick={() => cancelMutation.mutate()}>
                <XCircle className="h-4 w-4" />
                Cancel
              </Button>
            )}
            {canRecordPayment && <Button onClick={() => setPaymentDialogOpen(true)}>Record Payment</Button>}
          </div>
        }
      />

      <Card>
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div>
              <p className="text-lg font-bold text-foreground">{company?.name}</p>
              <p className="text-sm text-muted-foreground">Invoice</p>
            </div>
            <StatusBadge label={INVOICE_STATUS_LABEL[invoice.status]} variant={INVOICE_STATUS_VARIANT[invoice.status]} className="text-sm" />
          </div>

          <div className="mb-8 grid grid-cols-2 gap-6 sm:grid-cols-4">
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Bill To</p>
              <p className="mt-1 text-sm font-medium text-foreground">{customer?.name ?? `Customer #${invoice.customer_id}`}</p>
              <p className="text-sm text-muted-foreground">{customer?.phone}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Reference</p>
              <p className="mt-1 text-sm text-foreground">{invoice.reference_number}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Invoice Date</p>
              <p className="mt-1 text-sm text-foreground">{formatDate(invoice.invoice_date)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Due Date</p>
              <p className="mt-1 text-sm text-foreground">{formatDate(invoice.due_date)}</p>
            </div>
          </div>

          <table className="mb-6 w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Product</th>
                <th className="py-2 text-right">Qty</th>
                <th className="py-2 text-right">Unit Price</th>
                <th className="py-2 text-right">Discount</th>
                <th className="py-2 text-right">Line Total</th>
              </tr>
            </thead>
            <tbody>
              {invoice.items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">{productName(item.product_id)}</td>
                  <td className="py-2 text-right text-foreground">{Number(item.quantity)}</td>
                  <td className="py-2 text-right text-foreground">{formatCurrency(item.unit_price)}</td>
                  <td className="py-2 text-right text-foreground">{formatCurrency(item.discount_amount)}</td>
                  <td className="py-2 text-right font-medium text-foreground">{formatCurrency(item.line_total)}</td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="ml-auto w-full max-w-xs space-y-1.5">
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Subtotal</span>
              <span>{formatCurrency(invoice.subtotal)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Discount</span>
              <span>{formatCurrency(invoice.discount_amount)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Tax</span>
              <span>{formatCurrency(invoice.tax_amount)}</span>
            </div>
            <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold text-foreground">
              <span>Total</span>
              <span>{formatCurrency(invoice.total_amount)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Amount Paid</span>
              <span>{formatCurrency(invoice.amount_paid)}</span>
            </div>
            <div className="flex justify-between text-sm font-semibold text-danger">
              <span>Balance Due</span>
              <span>{formatCurrency(invoice.balance_due)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={paymentDialogOpen} onOpenChange={setPaymentDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Record Payment</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Balance due: <span className="font-semibold text-foreground">{formatCurrency(invoice.balance_due)}</span>
            </p>
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
              <Label>Amount</Label>
              <Input type="number" min="0.01" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} />
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
            <Button disabled={!paymentTypeId || !amount || paymentMutation.isPending} onClick={() => paymentMutation.mutate()}>
              Record Payment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
