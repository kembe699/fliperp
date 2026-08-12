import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { createPlatformInvoice, createPlatformQuotation } from '@/api/platform'
import { fetchActiveProducts } from '@/api/products'
import { fetchTaxRates } from '@/api/pos'
import { ensureCrmServiceProduct, fetchCrmServices } from '@/api/crm'
import { usePermissions } from '@/hooks/use-permissions'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { computeTotals } from '@/lib/sales-totals'
import type { PlatformLineItemInput } from '@/types/platform'

import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LineItemsEditor, type LineItemRow } from '@/components/sales/LineItemsEditor'

const emptyRow = (): LineItemRow => ({
  key: crypto.randomUUID(),
  product_id: null,
  description: '',
  quantity: 1,
  unit_price: null,
  tax_rate_id: null,
  discount_amount: 0,
})

interface PlatformBillingFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  clientId: number
  kind: 'invoice' | 'quotation'
}

export function PlatformBillingFormDialog({ open, onOpenChange, clientId, kind }: PlatformBillingFormDialogProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [dateValue, setDateValue] = useState('')
  const [notes, setNotes] = useState('')
  const [rows, setRows] = useState<LineItemRow[]>([emptyRow()])

  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })
  const { data: taxRates } = useQuery({ queryKey: ['tax-rates'], queryFn: fetchTaxRates })
  // Same mechanism as InvoiceFormPage/QuotationFormPage: a CRM service is lazily turned into
  // a real (non-stock-tracked) Product on first use, so it can be billed the same as any
  // other line item — no separate service_id column, no second invoicing path.
  const canUseCrmServices = can('crm-services.view')
  const { data: servicesPage } = useQuery({
    queryKey: ['crm-services-all'],
    queryFn: () => fetchCrmServices({ per_page: 100, is_active: true }),
    enabled: canUseCrmServices,
  })

  const resolveService = async (serviceId: number) => {
    const product = await ensureCrmServiceProduct(serviceId)
    queryClient.invalidateQueries({ queryKey: ['products-all'] })
    return product
  }

  const reset = () => {
    setDateValue('')
    setNotes('')
    setRows([emptyRow()])
  }

  const mutation = useMutation({
    mutationFn: async (): Promise<{ id: number }> => {
      const items: PlatformLineItemInput[] = rows
        .filter((row) => row.product_id)
        .map((row) => ({
          product_id: row.product_id!,
          description: row.description || null,
          quantity: row.quantity,
          unit_price: row.unit_price,
          tax_rate_id: row.tax_rate_id,
          discount_amount: row.discount_amount,
        }))

      if (kind === 'invoice') {
        return createPlatformInvoice(clientId, { due_date: dateValue, notes, items })
      }
      return createPlatformQuotation(clientId, { valid_until: dateValue, notes, items })
    },
    onSuccess: (result) => {
      toast.success(kind === 'invoice' ? 'Invoice created' : 'Quotation created')
      queryClient.invalidateQueries({ queryKey: ['platform-client', clientId] })
      reset()
      onOpenChange(false)
      navigate(kind === 'invoice' ? `/invoices/${result.id}` : `/quotations/${result.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const totals = computeTotals(rows, taxRates ?? [], 0)
  const canSubmit = dateValue && rows.some((row) => row.product_id)

  return (
    <Dialog open={open} onOpenChange={(next) => { if (!next) reset(); onOpenChange(next) }}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{kind === 'invoice' ? 'Create Invoice' : 'Create Quotation'}</DialogTitle>
        </DialogHeader>

        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault()
            mutation.mutate()
          }}
        >
          <div className="space-y-1">
            <Label htmlFor="billing-date">{kind === 'invoice' ? 'Due Date' : 'Valid Until'}</Label>
            <Input id="billing-date" type="date" required value={dateValue} onChange={(event) => setDateValue(event.target.value)} />
          </div>

          <LineItemsEditor
            rows={rows}
            onChange={setRows}
            products={products ?? []}
            taxRates={taxRates ?? []}
            services={canUseCrmServices ? servicesPage?.data : undefined}
            onResolveService={canUseCrmServices ? resolveService : undefined}
          />

          <div className="space-y-1 border-t border-border pt-3 text-sm">
            <div className="flex items-center justify-between text-muted-foreground">
              <span>Subtotal</span>
              <span>{formatCurrency(totals.subtotal)}</span>
            </div>
            <div className="flex items-center justify-between text-muted-foreground">
              <span>Tax</span>
              <span>{formatCurrency(totals.taxAmount)}</span>
            </div>
            <div className="flex items-center justify-between text-base font-semibold text-foreground">
              <span>Total</span>
              <span>{formatCurrency(totals.total)}</span>
            </div>
          </div>

          <DialogFooter>
            <Button type="submit" className="w-full" disabled={!canSubmit || mutation.isPending}>
              {mutation.isPending ? 'Creating…' : `Create ${kind === 'invoice' ? 'Invoice' : 'Quotation'}`}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
