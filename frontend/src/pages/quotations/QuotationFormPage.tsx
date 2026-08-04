import { useEffect, useState } from 'react'
import { useNavigate, useParams, Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createQuotation, fetchQuotation, sendQuotation, updateQuotation } from '@/api/quotations'
import { fetchBranches } from '@/api/branches'
import { fetchCustomers } from '@/api/customers'
import { fetchActiveProducts } from '@/api/products'
import { fetchPriceListItems, fetchPriceLists, fetchTaxRates } from '@/api/pos'
import { getApiErrorInfo } from '@/lib/api-errors'
import { computeTotals } from '@/lib/sales-totals'
import { useAuthStore } from '@/lib/auth-store'
import type { QuotationFormInput } from '@/types/quotation'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'
import { LineItemsEditor, type LineItemRow } from '@/components/sales/LineItemsEditor'
import { TotalsFooter } from '@/components/sales/TotalsFooter'

export function QuotationFormPage() {
  const { id } = useParams<{ id: string }>()
  const isEdit = !!id
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)

  const { data: existingQuotation, isLoading: quotationLoading } = useQuery({
    queryKey: ['quotation', id],
    queryFn: () => fetchQuotation(Number(id)),
    enabled: isEdit,
  })

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })
  const { data: taxRates } = useQuery({ queryKey: ['tax-rates'], queryFn: fetchTaxRates })
  const { data: priceLists } = useQuery({ queryKey: ['price-lists'], queryFn: fetchPriceLists })

  const [customerId, setCustomerId] = useState<string | null>(null)
  const [branchId, setBranchId] = useState('')
  const [priceListId, setPriceListId] = useState<string | null>(null)
  const [quotationDate, setQuotationDate] = useState('')
  const [validUntil, setValidUntil] = useState('')
  const [notes, setNotes] = useState('')
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
      setQuotationDate(new Date().toISOString().slice(0, 10))
      setRows([{ key: crypto.randomUUID(), product_id: null, quantity: 1, unit_price: null, tax_rate_id: null, discount_amount: 0 }])
      return
    }
    if (existingQuotation) {
      setCustomerId(String(existingQuotation.customer_id))
      setBranchId(String(existingQuotation.branch_id))
      setPriceListId(existingQuotation.price_list_id ? String(existingQuotation.price_list_id) : null)
      setQuotationDate(existingQuotation.quotation_date)
      setValidUntil(existingQuotation.valid_until)
      setNotes(existingQuotation.notes ?? '')
      setOrderDiscount(Number(existingQuotation.discount_amount))
      setRows(
        existingQuotation.items.map((item) => ({
          key: crypto.randomUUID(),
          product_id: item.product_id,
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
          tax_rate_id: item.tax_rate_id,
          discount_amount: Number(item.discount_amount),
        })),
      )
    }
  }, [isEdit, existingQuotation, user])

  const totals = computeTotals(rows, taxRates ?? [], orderDiscount)

  const buildPayload = (): QuotationFormInput => ({
    branch_id: Number(branchId),
    customer_id: Number(customerId),
    price_list_id: priceListId ? Number(priceListId) : undefined,
    quotation_date: quotationDate || undefined,
    valid_until: validUntil,
    notes: notes || undefined,
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
      return isEdit ? updateQuotation(Number(id), payload) : createQuotation(payload)
    },
    onSuccess: (quotation) => {
      toast.success(isEdit ? 'Quotation updated' : 'Quotation saved as draft')
      queryClient.invalidateQueries({ queryKey: ['quotations'] })
      navigate(`/quotations/${quotation.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const saveAndSendMutation = useMutation({
    mutationFn: async () => {
      const payload = buildPayload()
      const quotation = isEdit ? await updateQuotation(Number(id), payload) : await createQuotation(payload)
      return sendQuotation(quotation.id)
    },
    onSuccess: (quotation) => {
      toast.success('Quotation sent')
      queryClient.invalidateQueries({ queryKey: ['quotations'] })
      navigate(`/quotations/${quotation.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isEdit && quotationLoading) {
    return <div className="p-6 text-sm text-muted-foreground">Loading quotation…</div>
  }

  if (isEdit && existingQuotation && existingQuotation.status !== 'draft') {
    return <Navigate to={`/quotations/${id}`} replace />
  }

  const canSubmit = customerId && branchId && validUntil && rows.some((row) => row.product_id)

  return (
    <div>
      <PageHeader parent="Quotations" title={isEdit ? 'Edit Quotation' : 'New Quotation'} />

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
            <Label>Quotation Date</Label>
            <Input type="date" value={quotationDate} onChange={(event) => setQuotationDate(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Valid Until</Label>
            <Input type="date" value={validUntil} onChange={(event) => setValidUntil(event.target.value)} required />
          </div>
          <div className="space-y-1.5 sm:col-span-2 lg:col-span-4">
            <Label>Notes</Label>
            <Textarea value={notes} onChange={(event) => setNotes(event.target.value)} />
          </div>
        </CardContent>
      </Card>

      <div className="mb-4">
        <LineItemsEditor rows={rows} onChange={setRows} products={products ?? []} taxRates={taxRates ?? []} priceListItems={priceListItems} />
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
