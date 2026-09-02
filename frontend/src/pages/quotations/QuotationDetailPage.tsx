import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2, Download, FileOutput, Mail, Pencil, Send, XCircle } from 'lucide-react'

import { acceptQuotation, convertQuotationToInvoice, emailQuotation, fetchQuotation, rejectQuotation, sendQuotation } from '@/api/quotations'
import { fetchCustomers } from '@/api/customers'
import { fetchActiveProducts } from '@/api/products'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import { QUOTATION_STATUS_LABEL, QUOTATION_STATUS_VARIANT } from '@/components/sales/status-variants'
import { isQuotationEditable } from '@/types/quotation'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'

export function QuotationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const quotationId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const company = useAuthStore((state) => state.company)
  const { can } = usePermissions()

  const { data: quotation, isLoading } = useQuery({
    queryKey: ['quotation', id],
    queryFn: () => fetchQuotation(quotationId),
    enabled: !!quotationId,
  })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })
  const [downloading, setDownloading] = useState(false)

  const customer = customersPage?.data.find((c) => c.id === quotation?.customer_id)
  const productName = (productId: number) => products?.find((product) => product.id === productId)?.name ?? `Product #${productId}`

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['quotation', id] })
    queryClient.invalidateQueries({ queryKey: ['quotations'] })
  }

  const sendMutation = useMutation({
    mutationFn: () => sendQuotation(quotationId),
    onSuccess: () => {
      toast.success('Quotation sent')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const acceptMutation = useMutation({
    mutationFn: () => acceptQuotation(quotationId),
    onSuccess: () => {
      toast.success('Quotation accepted')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const rejectMutation = useMutation({
    mutationFn: () => rejectQuotation(quotationId),
    onSuccess: () => {
      toast.success('Quotation rejected')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const convertMutation = useMutation({
    mutationFn: () => convertQuotationToInvoice(quotationId),
    onSuccess: (invoice) => {
      toast.success('Converted to invoice')
      invalidate()
      navigate(`/invoices/${invoice.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const emailMutation = useMutation({
    mutationFn: () => emailQuotation(quotationId),
    onSuccess: (message) => toast.success(message),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownloadPdf = async () => {
    if (!quotation) return
    setDownloading(true)
    try {
      await downloadPdf(`/quotations/${quotationId}/pdf`, `quotation-${quotation.reference_number}.pdf`)
    } catch {
      toast.error('Could not download the quotation PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  if (isLoading || !quotation) {
    return <div className="p-6 text-sm text-muted-foreground">Loading quotation…</div>
  }

  const canEdit = can('quotations.update') && isQuotationEditable(quotation.status)
  const canSend = can('quotations.send') && quotation.status === 'draft'
  const canAccept = can('quotations.accept') && quotation.status === 'sent'
  const canReject = can('quotations.reject') && quotation.status === 'sent'
  const canConvert = can('quotations.convert-to-invoice') && quotation.status === 'accepted'

  return (
    <div>
      <PageHeader
        parent="Quotations"
        title={quotation.reference_number}
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={downloading} onClick={handleDownloadPdf}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download PDF'}
            </Button>
            {can('quotations.view') && (
              <Button variant="outline" disabled={emailMutation.isPending} onClick={() => emailMutation.mutate()}>
                <Mail className="h-4 w-4" />
                {emailMutation.isPending ? 'Sending…' : 'Email'}
              </Button>
            )}
            {canEdit && (
              <Button variant="outline" onClick={() => navigate(`/quotations/${quotationId}/edit`)}>
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
            {canReject && (
              <Button variant="outline" className="text-destructive" disabled={rejectMutation.isPending} onClick={() => rejectMutation.mutate()}>
                <XCircle className="h-4 w-4" />
                Reject
              </Button>
            )}
            {canAccept && (
              <Button variant="outline" disabled={acceptMutation.isPending} onClick={() => acceptMutation.mutate()}>
                <CheckCircle2 className="h-4 w-4" />
                Accept
              </Button>
            )}
            {canConvert && (
              <Button disabled={convertMutation.isPending} onClick={() => convertMutation.mutate()}>
                <FileOutput className="h-4 w-4" />
                Convert to Invoice
              </Button>
            )}
          </div>
        }
      />

      <Card>
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div className="flex items-center gap-3">
              <img
                src={company?.logo_url || '/logo.png'}
                onError={(event) => {
                  event.currentTarget.onerror = null
                  event.currentTarget.src = '/logo.png'
                }}
                alt={company?.name ?? 'Company logo'}
                className="h-10 w-auto object-contain"
              />
              <div>
                <p className="text-lg font-bold text-foreground">Quotation</p>
                {quotation.notes && <p className="text-sm text-muted-foreground">{quotation.notes}</p>}
              </div>
            </div>
            <StatusBadge label={QUOTATION_STATUS_LABEL[quotation.status]} variant={QUOTATION_STATUS_VARIANT[quotation.status]} className="text-sm" />
          </div>

          <div className="mb-8 grid grid-cols-2 gap-6 sm:grid-cols-4">
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Customer</p>
              <p className="mt-1 text-sm font-medium text-foreground">{customer?.name ?? `Customer #${quotation.customer_id}`}</p>
              <p className="text-sm text-muted-foreground">{customer?.phone}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Reference</p>
              <p className="mt-1 text-sm text-foreground">{quotation.reference_number}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Quotation Date</p>
              <p className="mt-1 text-sm text-foreground">{formatDate(quotation.quotation_date)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Valid Until</p>
              <p className="mt-1 text-sm text-foreground">{formatDate(quotation.valid_until)}</p>
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
              {quotation.items.map((item) => (
                <tr key={item.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">
                    {productName(item.product_id)}
                    {item.description && <p className="text-xs text-muted-foreground">{item.description}</p>}
                  </td>
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
              <span>{formatCurrency(quotation.subtotal)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Discount</span>
              <span>{formatCurrency(quotation.discount_amount)}</span>
            </div>
            <div className="flex justify-between text-sm text-muted-foreground">
              <span>Tax</span>
              <span>{formatCurrency(quotation.tax_amount)}</span>
            </div>
            <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold text-foreground">
              <span>Total</span>
              <span>{formatCurrency(quotation.total_amount)}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
