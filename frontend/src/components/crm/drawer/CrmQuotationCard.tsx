import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, Mail, Repeat } from 'lucide-react'

import { fetchQuotation, acceptQuotation, convertQuotationToInvoice, rejectQuotation, sendQuotation } from '@/api/quotations'
import { fetchActiveProducts } from '@/api/products'
import { fetchCrmCustomer } from '@/api/crm'
import { QUOTATION_STATUS_LABEL, QUOTATION_STATUS_VARIANT } from '@/components/sales/status-variants'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { downloadPdf } from '@/lib/pdf-download'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmQuotationSummary } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { SendQuotationToContactDialog } from '@/components/crm/drawer/SendQuotationToContactDialog'

interface CrmQuotationCardProps {
  summary: CrmQuotationSummary
  leadId?: number
  dealId?: number
  contactEmail?: string | null
  contactName?: string | null
  queryKeyToInvalidate: unknown[]
  emailsQueryKeyToInvalidate: unknown[]
}

export function CrmQuotationCard({ summary, leadId, dealId, contactEmail, contactName, queryKeyToInvalidate, emailsQueryKeyToInvalidate }: CrmQuotationCardProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [sendOpen, setSendOpen] = useState(false)

  const [convertedInvoiceId, setConvertedInvoiceId] = useState<number | null>(null)

  const { data: quotation } = useQuery({ queryKey: ['quotation', summary.id], queryFn: () => fetchQuotation(summary.id) })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  // Falls back to fetching the customer directly when the caller doesn't
  // already know the contact's email (deals only carry {id, name} for their
  // customer in the aggregated detail payload).
  const { data: fetchedCustomer } = useQuery({
    queryKey: ['crm-customer', quotation?.customer_id],
    queryFn: () => fetchCrmCustomer(quotation!.customer_id),
    enabled: !!quotation?.customer_id && !contactEmail,
  })

  const resolvedContactEmail = contactEmail ?? fetchedCustomer?.email ?? null
  const resolvedContactName = contactName ?? fetchedCustomer?.name ?? null

  const productName = (productId: number) => products?.find((p) => p.id === productId)?.name ?? `Product #${productId}`

  const invalidateAll = () => {
    queryClient.invalidateQueries({ queryKey: ['quotation', summary.id] })
    queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
  }

  const statusMutation = useMutation({
    mutationFn: (action: 'send' | 'accept' | 'reject') =>
      action === 'send' ? sendQuotation(summary.id) : action === 'accept' ? acceptQuotation(summary.id) : rejectQuotation(summary.id),
    onSuccess: () => {
      toast.success('Quotation status updated')
      invalidateAll()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const convertMutation = useMutation({
    mutationFn: () => convertQuotationToInvoice(summary.id),
    onSuccess: (invoice) => {
      toast.success('Converted to invoice')
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
      invalidateAll()
      setConvertedInvoiceId(invoice.id)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Card>
      <CardContent className="space-y-3 p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="font-medium text-foreground">{summary.reference_number}</p>
            <p className="text-sm text-muted-foreground">
              {formatCurrency(summary.total_amount)} · Valid until {summary.valid_until ? formatDate(summary.valid_until) : '—'}
            </p>
          </div>
          <StatusBadge label={QUOTATION_STATUS_LABEL[summary.status as keyof typeof QUOTATION_STATUS_LABEL] ?? summary.status} variant={QUOTATION_STATUS_VARIANT[summary.status as keyof typeof QUOTATION_STATUS_VARIANT] ?? 'neutral'} />
        </div>

        {quotation && (
          <div className="overflow-hidden rounded-lg border border-border">
            <table className="w-full text-xs">
              <tbody>
                {quotation.items.map((item) => (
                  <tr key={item.id} className="border-b border-border last:border-b-0">
                    <td className="px-3 py-1.5 text-foreground">{productName(item.product_id)}</td>
                    <td className="px-3 py-1.5 text-right text-muted-foreground">{item.quantity} × {formatCurrency(item.unit_price)}</td>
                    <td className="px-3 py-1.5 text-right text-foreground">{formatCurrency(item.line_total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2">
          {can('quotations.send') && summary.status === 'draft' && (
            <Button size="sm" variant="outline" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate('send')}>
              Mark as Sent
            </Button>
          )}
          {can('quotations.accept') && summary.status === 'sent' && (
            <Button size="sm" variant="outline" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate('accept')}>
              Mark as Accepted
            </Button>
          )}
          {can('quotations.reject') && summary.status === 'sent' && (
            <Button size="sm" variant="outline" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate('reject')}>
              Mark as Rejected
            </Button>
          )}
          <Button
            size="sm"
            variant="outline"
            onClick={() => downloadPdf(`/quotations/${summary.id}/pdf`, `quotation-${summary.reference_number}.pdf`)}
          >
            <Download className="h-3.5 w-3.5" />
            Download PDF
          </Button>
          {can('crm-emails.create') && (
            <Button size="sm" variant="outline" onClick={() => setSendOpen(true)}>
              <Mail className="h-3.5 w-3.5" />
              Send to Contact
            </Button>
          )}
          {can('quotations.convert-to-invoice') && summary.status !== 'converted' && (
            <Button size="sm" disabled={summary.status !== 'accepted' || convertMutation.isPending} onClick={() => convertMutation.mutate()}>
              <Repeat className="h-3.5 w-3.5" />
              Convert to Invoice
            </Button>
          )}
          {convertedInvoiceId && (
            <Button size="sm" variant="outline" onClick={() => navigate(`/invoices/${convertedInvoiceId}`)}>
              View Invoice →
            </Button>
          )}
        </div>
      </CardContent>

      {quotation && (
        <SendQuotationToContactDialog
          open={sendOpen}
          onOpenChange={setSendOpen}
          quotation={quotation}
          leadId={leadId}
          dealId={dealId}
          contactEmail={resolvedContactEmail}
          contactName={resolvedContactName}
          queryKeyToInvalidate={queryKeyToInvalidate}
          emailsQueryKeyToInvalidate={emailsQueryKeyToInvalidate}
        />
      )}
    </Card>
  )
}
