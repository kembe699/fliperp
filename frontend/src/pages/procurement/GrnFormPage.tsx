import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createGoodsReceivedNote, fetchPurchaseOrder, fetchReceivingStatus, fetchSuppliers } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { GrnItemCondition } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface Row {
  key: string
  product_id: number | null
  purchase_order_item_id: number | null
  quantity_received: number
  unit_cost: number
  condition: GrnItemCondition
}

const CONDITIONS: GrnItemCondition[] = ['good', 'damaged', 'rejected']

export function GrnFormPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  const purchaseOrderId = searchParams.get('purchase_order_id')

  const { data: po } = useQuery({
    queryKey: ['purchase-order', purchaseOrderId],
    queryFn: () => fetchPurchaseOrder(Number(purchaseOrderId)),
    enabled: !!purchaseOrderId,
  })
  const { data: receivingStatus } = useQuery({
    queryKey: ['po-receiving-status', purchaseOrderId],
    queryFn: () => fetchReceivingStatus(Number(purchaseOrderId)),
    enabled: !!purchaseOrderId,
  })
  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  const [supplierId, setSupplierId] = useState<string | null>(null)
  const [warehouseId, setWarehouseId] = useState('')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [receivedDate, setReceivedDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [notes, setNotes] = useState('')
  const [rows, setRows] = useState<Row[]>([{ key: crypto.randomUUID(), product_id: null, purchase_order_item_id: null, quantity_received: 1, unit_cost: 0, condition: 'good' }])

  useEffect(() => {
    if (!po || !receivingStatus) return
    setSupplierId(String(po.supplier_id))
    setWarehouseId(String(po.warehouse_id))
    // Unlike supplier/warehouse/items, reference_number has no PO-derived
    // value to copy — it's a required field the backend won't default for
    // us (unlike Invoice's reference_number, which auto-generates server
    // side if omitted). Leaving it blank meant every other field filled
    // itself in from the PO except this one, so the submit button looked
    // permanently disabled with no visible cause. Pre-fill a sensible,
    // still-editable default instead.
    setReferenceNumber((prev) => prev || `GRN-${po.reference_number}-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`)
    const outstandingRows = receivingStatus.items
      .filter((item) => item.quantity_outstanding > 0)
      .map((item) => {
        const poItem = po.items.find((i) => i.id === item.purchase_order_item_id)
        return {
          key: crypto.randomUUID(),
          product_id: item.product_id,
          purchase_order_item_id: item.purchase_order_item_id,
          quantity_received: item.quantity_outstanding,
          unit_cost: poItem ? Number(poItem.unit_cost) : 0,
          condition: 'good' as GrnItemCondition,
        }
      })
    if (outstandingRows.length > 0) setRows(outstandingRows)
  }, [po, receivingStatus])

  const addRow = () => setRows((prev) => [...prev, { key: crypto.randomUUID(), product_id: null, purchase_order_item_id: null, quantity_received: 1, unit_cost: 0, condition: 'good' }])
  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))
  const updateRow = (key: string, patch: Partial<Row>) => setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const mutation = useMutation({
    mutationFn: () =>
      createGoodsReceivedNote({
        purchase_order_id: purchaseOrderId ? Number(purchaseOrderId) : null,
        warehouse_id: Number(warehouseId),
        supplier_id: Number(supplierId),
        reference_number: referenceNumber,
        received_date: receivedDate,
        notes: notes || null,
        items: rows
          .filter((row) => row.product_id)
          .map((row) => ({
            product_id: row.product_id!,
            purchase_order_item_id: row.purchase_order_item_id,
            quantity_received: row.quantity_received,
            unit_cost: row.unit_cost,
            condition: row.condition,
          })),
      }),
    onSuccess: (grn) => {
      toast.success('Goods received note created')
      queryClient.invalidateQueries({ queryKey: ['goods-received-notes'] })
      if (purchaseOrderId) queryClient.invalidateQueries({ queryKey: ['purchase-order', purchaseOrderId] })
      navigate(`/goods-received-notes/${grn.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = supplierId && warehouseId && referenceNumber && receivedDate && rows.some((row) => row.product_id)

  return (
    <div>
      <PageHeader parent="Goods Received Notes" title={po ? `Receive Goods — PO ${po.reference_number}` : 'New Goods Received Note'} />

      <Card className="mb-4">
        <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5 lg:col-span-2">
            <Label>Supplier</Label>
            <SearchableSelect
              options={(suppliers?.data ?? []).map((supplier) => ({ value: String(supplier.id), label: supplier.name }))}
              value={supplierId}
              onChange={setSupplierId}
              placeholder="Select supplier"
              disabled={!!purchaseOrderId}
            />
          </div>
          <div className="space-y-1.5">
            <Label>Warehouse</Label>
            <Select value={warehouseId} onValueChange={setWarehouseId} disabled={!!purchaseOrderId}>
              <SelectTrigger>
                <SelectValue placeholder="Select warehouse" />
              </SelectTrigger>
              <SelectContent>
                {warehouses?.map((warehouse) => (
                  <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                    {warehouse.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Reference Number</Label>
            <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="GRN-001" />
          </div>
          <div className="space-y-1.5">
            <Label>Received Date</Label>
            <Input type="date" value={receivedDate} onChange={(event) => setReceivedDate(event.target.value)} />
          </div>
          <div className="space-y-1.5 lg:col-span-3">
            <Label>Notes</Label>
            <Input value={notes} onChange={(event) => setNotes(event.target.value)} />
          </div>
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="space-y-3 p-6">
          <p className="text-sm font-semibold text-foreground">Items Received</p>
          {rows.map((row) => (
            <div key={row.key} className="flex items-center gap-2">
              <div className="flex-1">
                <SearchableSelect
                  options={(products ?? []).map((product) => ({ value: String(product.id), label: product.name, sublabel: product.sku }))}
                  value={row.product_id ? String(row.product_id) : null}
                  onChange={(value) => updateRow(row.key, { product_id: value ? Number(value) : null })}
                  placeholder="Select product"
                  disabled={!!row.purchase_order_item_id}
                />
              </div>
              <Input
                type="number"
                min="1"
                step="1"
                value={row.quantity_received}
                onChange={(event) => updateRow(row.key, { quantity_received: Number(event.target.value) })}
                className="w-24"
                placeholder="Qty"
              />
              <Input
                type="number"
                min="0"
                step="0.01"
                value={row.unit_cost}
                onChange={(event) => updateRow(row.key, { unit_cost: Number(event.target.value) })}
                className="w-28"
                placeholder="Unit Cost"
              />
              <Select value={row.condition} onValueChange={(value) => updateRow(row.key, { condition: value as GrnItemCondition })}>
                <SelectTrigger className="w-32">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CONDITIONS.map((condition) => (
                    <SelectItem key={condition} value={condition}>
                      {condition}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {!row.purchase_order_item_id && (
                <button type="button" onClick={() => removeRow(row.key)} className="text-muted-foreground hover:text-destructive">
                  <Trash2 className="h-4 w-4" />
                </button>
              )}
            </div>
          ))}
          {!purchaseOrderId && (
            <Button type="button" variant="outline" size="sm" onClick={addRow}>
              <Plus className="h-4 w-4" />
              Add Item
            </Button>
          )}
        </CardContent>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => navigate(-1)}>
          Cancel
        </Button>
        <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
          Create Goods Received Note
        </Button>
      </div>
    </div>
  )
}
