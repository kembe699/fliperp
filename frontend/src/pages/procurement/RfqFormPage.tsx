import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createRfq, fetchRfq, updateRfq } from '@/api/rfqs'
import { fetchSuppliers } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { getApiErrorInfo } from '@/lib/api-errors'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface Row {
  key: number
  product_id: string | null
  quantity: string
  description: string
}

const NONE = '__none'
const localToday = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
let nextKey = 1
const blankRow = (): Row => ({ key: nextKey++, product_id: null, quantity: '1', description: '' })

/** New or edit (draft only) request for quotation: what is needed and which suppliers to ask. */
export function RfqFormPage() {
  const { id } = useParams<{ id: string }>()
  const editingId = id ? Number(id) : null
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: existing } = useQuery({ queryKey: ['rfq', editingId], queryFn: () => fetchRfq(editingId!), enabled: !!editingId })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: products = [] } = useQuery({ queryKey: ['products-active-all'], queryFn: () => fetchActiveProducts({ per_page: 1000 }) })
  const { data: warehouses = [] } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })

  const [title, setTitle] = useState('')
  const [issueDate, setIssueDate] = useState(localToday)
  const [dueDate, setDueDate] = useState('')
  const [warehouseId, setWarehouseId] = useState(NONE)
  const [notes, setNotes] = useState('')
  const [rows, setRows] = useState<Row[]>([blankRow()])
  const [supplierIds, setSupplierIds] = useState<number[]>([])
  const [supplierSearch, setSupplierSearch] = useState('')

  useEffect(() => {
    if (!existing) return
    setTitle(existing.title)
    setIssueDate(existing.issue_date)
    setDueDate(existing.response_due_date ?? '')
    setWarehouseId(existing.warehouse_id ? String(existing.warehouse_id) : NONE)
    setNotes(existing.notes ?? '')
    setRows((existing.items ?? []).map((item) => ({ key: nextKey++, product_id: String(item.product_id), quantity: String(item.quantity), description: item.description ?? '' })))
    setSupplierIds((existing.suppliers ?? []).map((s) => s.supplier_id))
  }, [existing])

  const productOptions = useMemo(() => products.map((p) => ({ value: String(p.id), label: p.name, sublabel: p.sku ?? undefined })), [products])
  const activeSuppliers = (suppliers?.data ?? []).filter((s) => s.is_active !== false)
  const shownSuppliers = activeSuppliers.filter((s) => !supplierSearch || s.name.toLowerCase().includes(supplierSearch.toLowerCase()))
  const allChosen = activeSuppliers.length > 0 && activeSuppliers.every((s) => supplierIds.includes(s.id))

  const setRow = (key: number, patch: Partial<Row>) => setRows((list) => list.map((r) => (r.key === key ? { ...r, ...patch } : r)))
  const toggleSupplier = (sid: number) => setSupplierIds((list) => (list.includes(sid) ? list.filter((x) => x !== sid) : [...list, sid]))

  const validRows = rows.filter((r) => r.product_id && Number(r.quantity) > 0)
  const canSave = title.trim() && validRows.length > 0 && supplierIds.length > 0

  const mutation = useMutation({
    mutationFn: () => {
      const values = {
        title: title.trim(),
        issue_date: issueDate,
        response_due_date: dueDate || null,
        warehouse_id: warehouseId === NONE ? null : Number(warehouseId),
        notes: notes.trim() || null,
        items: validRows.map((r) => ({ product_id: Number(r.product_id), quantity: Number(r.quantity), description: r.description.trim() || null })),
        supplier_ids: supplierIds,
      }
      return editingId ? updateRfq(editingId, values) : createRfq(values)
    },
    onSuccess: (rfq) => {
      toast.success(editingId ? 'RFQ updated' : `RFQ ${rfq.reference_number} created — send it to the suppliers next`)
      queryClient.invalidateQueries({ queryKey: ['rfqs'] })
      queryClient.invalidateQueries({ queryKey: ['rfq', rfq.id] })
      navigate(`/rfqs/${rfq.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <div>
      <PageHeader parent="Requests for Quotation" title={editingId ? `Edit ${existing?.reference_number ?? 'RFQ'}` : 'New Request for Quotation'} />

      <Card className="mb-4">
        <CardContent className="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5 sm:col-span-2">
            <Label required>Title</Label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Office supplies for November" maxLength={255} />
          </div>
          <div className="space-y-1.5">
            <Label>Issue date</Label>
            <Input type="date" value={issueDate} onChange={(e) => setIssueDate(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Quotes due by</Label>
            <Input type="date" value={dueDate} min={issueDate} onChange={(e) => setDueDate(e.target.value)} />
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label>Deliver to (warehouse)</Label>
            <Select value={warehouseId} onValueChange={setWarehouseId}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>Choose later</SelectItem>
                {warehouses.map((w) => (
                  <SelectItem key={w.id} value={String(w.id)}>
                    {w.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label>Notes for suppliers</Label>
            <Input value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Delivery terms, payment terms, specifications…" />
          </div>
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="space-y-3 p-6">
          <p className="text-sm font-semibold text-foreground">Items needed</p>
          {rows.map((row) => (
            <div key={row.key} className="grid gap-2 sm:grid-cols-[2fr_110px_2fr_40px] sm:items-end">
              <div className="space-y-1.5">
                <Label className="sm:hidden">Product</Label>
                <SearchableSelect options={productOptions} value={row.product_id} onChange={(v) => setRow(row.key, { product_id: v })} placeholder="Choose a product" />
              </div>
              <div className="space-y-1.5">
                <Label className="sm:hidden">Quantity</Label>
                <Input type="number" min={0} step="any" value={row.quantity} onChange={(e) => setRow(row.key, { quantity: e.target.value })} placeholder="Qty" />
              </div>
              <Input value={row.description} onChange={(e) => setRow(row.key, { description: e.target.value })} placeholder="Specification (optional)" maxLength={1000} />
              <Button variant="ghost" size="sm" className="h-9 w-9 p-0 text-muted-foreground hover:text-destructive" disabled={rows.length === 1} onClick={() => setRows((list) => list.filter((r) => r.key !== row.key))} aria-label="Remove item">
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          ))}
          <Button variant="outline" size="sm" onClick={() => setRows((list) => [...list, blankRow()])}>
            <Plus className="h-4 w-4" />
            Add item
          </Button>
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="space-y-3 p-6">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm font-semibold text-foreground">
              Suppliers to ask <span className="font-normal text-muted-foreground">({supplierIds.length} chosen)</span>
            </p>
            <div className="flex items-center gap-2">
              <Input value={supplierSearch} onChange={(e) => setSupplierSearch(e.target.value)} placeholder="Search suppliers" className="h-8 w-48" />
              <Button variant="outline" size="sm" onClick={() => setSupplierIds(allChosen ? [] : activeSuppliers.map((s) => s.id))}>
                {allChosen ? 'Clear all' : 'Select all'}
              </Button>
            </div>
          </div>
          {activeSuppliers.length === 0 ? (
            <p className="text-sm text-muted-foreground">No suppliers yet — add them under Procurement › Suppliers.</p>
          ) : (
            <div className="grid max-h-72 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
              {shownSuppliers.map((s) => (
                <label key={s.id} className="flex cursor-pointer items-start gap-2 rounded-lg border border-border p-2.5 hover:bg-accent/40">
                  <input type="checkbox" className="mt-1" checked={supplierIds.includes(s.id)} onChange={() => toggleSupplier(s.id)} />
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-medium text-foreground">{s.name}</span>
                    <span className={`block truncate text-xs ${s.email ? 'text-muted-foreground' : 'text-warning'}`}>{s.email ?? 'No email — can’t be emailed'}</span>
                  </span>
                </label>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => navigate(-1)}>
          Cancel
        </Button>
        <Button disabled={!canSave || mutation.isPending} onClick={() => mutation.mutate()}>
          {mutation.isPending ? 'Saving…' : editingId ? 'Save changes' : 'Create RFQ'}
        </Button>
      </div>
    </div>
  )
}
