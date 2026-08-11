import { useEffect, useState } from 'react'
import { useNavigate, useParams, Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createInvoice, fetchInvoice, sendInvoice, updateInvoice } from '@/api/invoices'
import { fetchBranches } from '@/api/branches'
import { fetchCustomers } from '@/api/customers'
import { fetchActiveProducts } from '@/api/products'
import { fetchPriceListItems, fetchPriceLists, fetchTaxRates } from '@/api/pos'
import { ensureCrmServiceProduct, fetchCrmServices } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { computeTotals } from '@/lib/sales-totals'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import type { InvoiceFormInput } from '@/types/invoice'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'
import { LineItemsEditor, type LineItemRow } from '@/components/sales/LineItemsEditor'
import { TotalsFooter } from '@/components/sales/TotalsFooter'

export function InvoiceFormPage() {
  const { id } = useParams<{ id: string }>()
  const isEdit = !!id
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const { can } = usePermissions()

  const { data: existingInvoice, isLoading: invoiceLoading } = useQuery({
    queryKey: ['invoice', id],
    queryFn: () => fetchInvoice(Number(id)),
    enabled: isEdit,
  })

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })
  const { data: taxRates } = useQuery({ queryKey: ['tax-rates'], queryFn: fetchTaxRates })
  const { data: priceLists } = useQuery({ queryKey: ['price-lists'], queryFn: fetchPriceLists })
  // Lets an invoice pull line items straight from the CRM service catalog, not just Products —
  // omitted for users without CRM access so the picker quietly falls back to products-only.
  const canUseCrmServices = can('crm-services.view')
  const { data: servicesPage } = useQuery({
    queryKey: ['crm-services-all'],
    queryFn: () => fetchCrmServices({ per_page: 100, is_active: true }),
    enabled: canUseCrmServices,
  })

  const [customerId, setCustomerId] = useState<string | null>(null)
  const [branchId, setBranchId] = useState<string>('')
  const [priceListId, setPriceListId] = useState<string | null>(null)
  const [invoiceDate, setInvoiceDate] = useState('')
  const [dueDate, setDueDate] = useState('')
  const [orderDiscount, setOrderDiscount] = useState(0)
  const [rows, setRows] = useState<LineItemRow[]>([])

  const { data: priceListItems } = useQuery({
    queryKey: ['price-list-items', priceListId],
    queryFn: () => fetchPriceListItems(Number(priceListId)),
    enabled: !!priceListId,
  })

  useEffect(() => {
    if (!isEdit) {
      setBranchId(user?.branch_id ? String(user.branch_id) : '')
      setInvoiceDate(new Date().toISOString().slice(0, 10))
      setRows([{ key: crypto.randomUUID(), product_id: null, quantity: 1, unit_price: null, tax_rate_id: null, discount_amount: 0 }])
      return
    }
    if (existingInvoice) {
      setCustomerId(String(existingInvoice.customer_id))
      setBranchId(String(existingInvoice.branch_id))
      setInvoiceDate(existingInvoice.invoice_date)
      setDueDate(existingInvoice.due_date)
      setOrderDiscount(Number(existingInvoice.discount_amount))
      setRows(
        existingInvoice.items.map((item) => ({
          key: crypto.randomUUID(),
          product_id: item.product_id,
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
          tax_rate_id: item.tax_rate_id,
          discount_amount: Number(item.discount_amount),
        })),
      )
    }
  }, [isEdit, existingInvoice, user])

  const totals = computeTotals(rows, taxRates ?? [], orderDiscount)

  const buildPayload = (): InvoiceFormInput => ({
    branch_id: Number(branchId),
    customer_id: Number(customerId),
    price_list_id: priceListId ? Number(priceListId) : undefined,
    invoice_date: invoiceDate || undefined,
    due_date: dueDate,
    discount_amount: orderDiscount,
    items: rows
      .filter((row) => row.product_id)
      .map((row) => ({
        product_id: row.product_id!,
        quantity: row.quantity,
        unit_price: row.unit_price,
        tax_rate_id: row.tax_rate_id,
        discount_amount: row.discount_amount,
      })),
  })

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = buildPayload()
      return isEdit ? updateInvoice(Number(id), payload) : createInvoice(payload)
    },
    onSuccess: (invoice) => {
      toast.success(isEdit ? 'Invoice updated' : 'Invoice saved as draft')
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
      navigate(`/invoices/${invoice.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const saveAndSendMutation = useMutation({
    mutationFn: async () => {
      const payload = buildPayload()
      const invoice = isEdit ? await updateInvoice(Number(id), payload) : await createInvoice(payload)
      return sendInvoice(invoice.id)
    },
    onSuccess: (invoice) => {
      toast.success('Invoice sent')
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
      navigate(`/invoices/${invoice.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const resolveService = async (serviceId: number) => {
    const product = await ensureCrmServiceProduct(serviceId)
    queryClient.invalidateQueries({ queryKey: ['products-all'] })
    return product
  }

  if (isEdit && invoiceLoading) {
    return <div className="p-6 text-sm text-muted-foreground">Loading invoice…</div>
  }

  if (isEdit && existingInvoice && existingInvoice.status !== 'draft') {
    return <Navigate to={`/invoices/${id}`} replace />
  }

  const canSubmit = customerId && branchId && dueDate && rows.some((row) => row.product_id)

  return (
    <div>
      <PageHeader parent="Invoices" title={isEdit ? 'Edit Invoice' : 'New Invoice'} />

      <Card className="mb-4">
        <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5 lg:col-span-2">
            <Label>Customer</Label>
            <SearchableSelect
              options={(customersPage?.data ?? []).map((customer) => ({ value: String(customer.id), label: customer.name, sublabel: customer.phone }))}
              value={customerId}
              onChange={setCustomerId}
              placeholder="Select customer"
            />
          </div>
          <div className="space-y-1.5">
            <Label>Branch</Label>
            <Select value={branchId} onValueChange={setBranchId}>
              <SelectTrigger>
                <SelectValue placeholder="Select branch" />
              </SelectTrigger>
              <SelectContent>
                {branches?.map((branch) => (
                  <SelectItem key={branch.id} value={String(branch.id)}>
                    {branch.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Price List</Label>
            <Select value={priceListId ?? 'none'} onValueChange={(value) => setPriceListId(value === 'none' ? null : value)}>
              <SelectTrigger>
                <SelectValue placeholder="None" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">None</SelectItem>
                {priceLists?.map((priceList) => (
                  <SelectItem key={priceList.id} value={String(priceList.id)}>
                    {priceList.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Invoice Date</Label>
            <Input type="date" value={invoiceDate} onChange={(event) => setInvoiceDate(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Due Date</Label>
            <Input type="date" value={dueDate} onChange={(event) => setDueDate(event.target.value)} required />
          </div>
          {existingInvoice?.quotation_id && (
            <div className="space-y-1.5 self-end">
              <p className="text-xs text-muted-foreground">Converted from quotation #{existingInvoice.quotation_id}</p>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="mb-4">
        <LineItemsEditor
          rows={rows}
          onChange={setRows}
          products={products ?? []}
          taxRates={taxRates ?? []}
          priceListItems={priceListItems}
          services={canUseCrmServices ? servicesPage?.data : undefined}
          onResolveService={canUseCrmServices ? resolveService : undefined}
        />
      </div>

      <div className="mb-6 flex justify-end">
        <TotalsFooter {...totals} onDiscountChange={setOrderDiscount} />
      </div>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => navigate(-1)}>
          Cancel
        </Button>
        <Button variant="outline" disabled={!canSubmit || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
          Save as Draft
        </Button>
        <Button disabled={!canSubmit || saveAndSendMutation.isPending} onClick={() => saveAndSendMutation.mutate()}>
          Save & Send
        </Button>
      </div>
    </div>
  )
}
