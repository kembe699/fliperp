import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Paperclip, X } from 'lucide-react'

import { createPaymentRequest, fetchPaymentRequest, updatePaymentRequest, uploadPaymentRequestAttachments, type PaymentRequestInput } from '@/api/paymentRequests'
import { fetchPurchaseOrders, fetchSupplierBills, fetchSuppliers } from '@/api/procurement'
import { fetchRfqs } from '@/api/rfqs'
import { fetchChartOfAccounts } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import { EMPTY_CODING, type Coding } from '@/types/grants'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { CodingFields } from '@/components/shared/CodingFields'
import { CurrencyRateFields } from '@/components/shared/CurrencyRateFields'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

const today = () => new Date().toISOString().slice(0, 10)
const asId = (value: string | null) => (value ? Number(value) : null)

/** New payment request, or edit one that is still a draft or was rejected. */
export function PaymentRequestFormPage() {
  const { id } = useParams<{ id: string }>()
  const editingId = id ? Number(id) : null
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const baseCurrency = useAuthStore((state) => state.company?.currency_code) ?? 'USD'

  const { data: existing } = useQuery({ queryKey: ['payment-request', editingId], queryFn: () => fetchPaymentRequest(editingId as number), enabled: !!editingId })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }), enabled: can('suppliers.view') })
  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts, enabled: can('chart-of-accounts.view') })
  const { data: bills } = useQuery({ queryKey: ['supplier-bills', 'open'], queryFn: () => fetchSupplierBills({ per_page: 200 }), enabled: can('supplier-bills.view') })
  const { data: orders } = useQuery({ queryKey: ['purchase-orders', 'for-payment'], queryFn: () => fetchPurchaseOrders({ per_page: 200 }), enabled: can('purchase-orders.view') })
  const { data: rfqs } = useQuery({ queryKey: ['rfqs'], queryFn: fetchRfqs, enabled: can('purchase-orders.view') })

  const [requestDate, setRequestDate] = useState(today())
  const [payeeMode, setPayeeMode] = useState<'supplier' | 'other'>('other')
  const [supplierId, setSupplierId] = useState<string | null>(null)
  const [payeeName, setPayeeName] = useState('')
  const [description, setDescription] = useState('')
  const [amount, setAmount] = useState('')
  const [currency, setCurrency] = useState(baseCurrency)
  const [rate, setRate] = useState('1')
  const [expenseAccountId, setExpenseAccountId] = useState<string | null>(null)
  const [coding, setCoding] = useState<Coding>(EMPTY_CODING)
  const [billId, setBillId] = useState<string | null>(null)
  const [poId, setPoId] = useState<string | null>(null)
  const [rfqId, setRfqId] = useState<string | null>(null)
  const [files, setFiles] = useState<File[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})

  // Load the request being edited into the form once.
  useEffect(() => {
    if (!existing) return
    setRequestDate(existing.request_date)
    setPayeeMode(existing.supplier_id ? 'supplier' : 'other')
    setSupplierId(existing.supplier_id ? String(existing.supplier_id) : null)
    setPayeeName(existing.payee_name ?? '')
    setDescription(existing.description)
    setAmount(String(existing.amount))
    setCurrency(existing.currency_code)
    setRate(String(existing.exchange_rate))
    setExpenseAccountId(existing.expense_account_id ? String(existing.expense_account_id) : null)
    setCoding({ grant_id: existing.grant_id, dea_id: existing.dea_id, project_id: existing.project_id, cost_centre_id: existing.cost_centre_id })
    setBillId(existing.supplier_bill_id ? String(existing.supplier_bill_id) : null)
    setPoId(existing.purchase_order_id ? String(existing.purchase_order_id) : null)
    setRfqId(existing.rfq_id ? String(existing.rfq_id) : null)
  }, [existing])

  const expenseAccounts = (accounts ?? []).filter((account) => account.is_active && (account.type === 'expense' || account.type === 'asset'))
  const openBills = (bills?.data ?? []).filter((bill) => bill.status !== 'paid' || String(bill.id) === billId)
  const selectedBill = openBills.find((bill) => String(bill.id) === billId)

  // A bill decides the supplier, currency and (by default) the amount still owed.
  const chooseBill = (value: string | null) => {
    setBillId(value)
    const bill = (bills?.data ?? []).find((b) => String(b.id) === value)
    if (bill) {
      setPayeeMode('supplier')
      setSupplierId(String(bill.supplier_id))
      setCurrency(bill.currency_code ?? baseCurrency)
      if (!amount) setAmount(String(Math.round((bill.total_amount - bill.amount_paid) * 100) / 100))
    }
  }

  const baseEquivalent = useMemo(() => {
    const value = Number(amount)
    const r = Number(rate)
    return value > 0 && r > 0 ? value / r : null
  }, [amount, rate])

  const mutation = useMutation({
    mutationFn: async () => {
      const values: PaymentRequestInput = {
        request_date: requestDate,
        supplier_id: payeeMode === 'supplier' ? asId(supplierId) : null,
        payee_name: payeeMode === 'other' ? payeeName : null,
        description,
        amount: Number(amount),
        currency_code: currency,
        exchange_rate: rate ? Number(rate) : null,
        expense_account_id: billId ? null : asId(expenseAccountId),
        ...coding,
        supplier_bill_id: asId(billId),
        purchase_order_id: asId(poId),
        rfq_id: asId(rfqId),
      }
      const saved = editingId ? await updatePaymentRequest(editingId, values) : await createPaymentRequest(values)
      if (files.length > 0) await uploadPaymentRequestAttachments(saved.id, files)
      return saved
    },
    onSuccess: (saved) => {
      toast.success(editingId ? 'Payment request updated' : `Payment request ${saved.reference_number} created`)
      queryClient.invalidateQueries({ queryKey: ['payment-requests'] })
      queryClient.invalidateQueries({ queryKey: ['payment-request', saved.id] })
      navigate(`/payment-requests/${saved.id}`)
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      if (info.errors) {
        setErrors(Object.fromEntries(Object.entries(info.errors).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : String(messages)])))
      }
      toast.error(info.message)
    },
  })

  const fieldError = (key: string) => errors[key] && <p className="text-xs text-destructive">{errors[key]}</p>

  return (
    <div>
      <PageHeader parent="Payment Requests" title={editingId ? `Edit ${existing?.reference_number ?? ''}` : 'New Payment Request'} />

      <form
        onSubmit={(event) => {
          event.preventDefault()
          setErrors({})
          mutation.mutate()
        }}
      >
        <Card className="mb-4">
          <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="request_date" required>Date</Label>
              <Input id="request_date" type="date" value={requestDate} onChange={(event) => setRequestDate(event.target.value)} />
              {fieldError('request_date')}
            </div>
            <div />

            <div className="space-y-1.5 sm:col-span-2">
              <Label required>Pay to</Label>
              <div className="mb-2 flex gap-2">
                <Button type="button" size="sm" variant={payeeMode === 'supplier' ? 'default' : 'outline'} onClick={() => setPayeeMode('supplier')} disabled={!!billId}>
                  A supplier
                </Button>
                <Button type="button" size="sm" variant={payeeMode === 'other' ? 'default' : 'outline'} onClick={() => setPayeeMode('other')} disabled={!!billId}>
                  Someone else
                </Button>
              </div>
              {payeeMode === 'supplier' ? (
                <SearchableSelect
                  options={(suppliers?.data ?? []).map((supplier) => ({ value: String(supplier.id), label: supplier.name, sublabel: supplier.contact_person ?? undefined }))}
                  value={supplierId}
                  onChange={setSupplierId}
                  placeholder="Select supplier"
                  disabled={!!billId}
                />
              ) : (
                <Input placeholder="Payee name, e.g. staff member or landlord" value={payeeName} onChange={(event) => setPayeeName(event.target.value)} />
              )}
              {fieldError('payee_name')}
              {fieldError('supplier_id')}
            </div>

            <div className="space-y-1.5 sm:col-span-2">
              <Label htmlFor="description" required>Purpose</Label>
              <Textarea id="description" rows={3} placeholder="What the payment is for" value={description} onChange={(event) => setDescription(event.target.value)} />
              {fieldError('description')}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="amount" required>Amount</Label>
              <Input id="amount" type="number" min="0" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} />
              {fieldError('amount')}
              {currency !== baseCurrency && baseEquivalent !== null && (
                <p className="text-xs text-muted-foreground">≈ {formatCurrency(baseEquivalent, baseCurrency)} at today's rate. This decides who must approve it.</p>
              )}
            </div>
            <div />
            <CurrencyRateFields currency={currency} onCurrencyChange={billId ? undefined : setCurrency} date={requestDate} rate={rate} onRateChange={setRate} />
            {fieldError('exchange_rate')}

            {!billId && (
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Expense account</Label>
                <SearchableSelect
                  options={expenseAccounts.map((account) => ({ value: String(account.id), label: `${account.code} — ${account.name}` }))}
                  value={expenseAccountId}
                  onChange={setExpenseAccountId}
                  placeholder="Default: General Operating Expenses"
                />
                {fieldError('expense_account_id')}
              </div>
            )}
            <div className="space-y-2 sm:col-span-2">
              <p className="text-sm font-medium">Coding</p>
              <CodingFields value={coding} date={requestDate} onChange={(patch) => setCoding((prev) => ({ ...prev, ...patch }))} className="lg:grid-cols-2" />
              {fieldError('grant_id')}
            </div>
          </CardContent>
        </Card>

        <Card className="mb-4">
          <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-3">
            <p className="text-sm font-medium sm:col-span-3">Linked documents (optional)</p>
            {can('supplier-bills.view') && (
              <div className="space-y-1.5">
                <Label>Supplier bill</Label>
                <SearchableSelect
                  options={openBills.map((bill) => ({ value: String(bill.id), label: bill.reference_number, sublabel: `${formatCurrency(bill.total_amount - bill.amount_paid, bill.currency_code)} still owed` }))}
                  value={billId}
                  onChange={chooseBill}
                  placeholder="None"
                />
                {selectedBill && <p className="text-xs text-muted-foreground">Paying this request settles the bill.</p>}
                {fieldError('supplier_bill_id')}
              </div>
            )}
            {can('purchase-orders.view') && (
              <>
                <div className="space-y-1.5">
                  <Label>Purchase order</Label>
                  <SearchableSelect
                    options={(orders?.data ?? []).filter((po) => po.status !== 'cancelled').map((po) => ({ value: String(po.id), label: po.reference_number }))}
                    value={poId}
                    onChange={setPoId}
                    placeholder="None"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Request for quotation</Label>
                  <SearchableSelect
                    options={(rfqs?.data ?? []).map((rfq) => ({ value: String(rfq.id), label: rfq.reference_number, sublabel: rfq.title }))}
                    value={rfqId}
                    onChange={setRfqId}
                    placeholder="None"
                  />
                </div>
              </>
            )}

            <div className="space-y-1.5 sm:col-span-3">
              <Label>Supporting documents</Label>
              <Input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" onChange={(event) => setFiles([...files, ...Array.from(event.target.files ?? [])])} />
              {files.length > 0 && (
                <ul className="space-y-1 text-sm">
                  {files.map((file, index) => (
                    <li key={`${file.name}-${index}`} className="flex items-center gap-2">
                      <Paperclip className="h-3.5 w-3.5 text-muted-foreground" />
                      {file.name}
                      <button type="button" className="text-muted-foreground hover:text-destructive" onClick={() => setFiles(files.filter((_, i) => i !== index))} aria-label={`Remove ${file.name}`}>
                        <X className="h-3.5 w-3.5" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
              <p className="text-xs text-muted-foreground">Quotes, invoices or receipts. PDF, images, Word or Excel, up to 10 MB each.</p>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => navigate(-1)}>
            Cancel
          </Button>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Saving…' : editingId ? 'Save changes' : 'Save as draft'}
          </Button>
        </div>
      </form>
    </div>
  )
}
