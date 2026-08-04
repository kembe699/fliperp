import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createPurchaseOrder, submitPurchaseOrder } from '@/api/procurement'
import { fetchSuppliers } from '@/api/procurement'
import { fetchWarehouses } from '@/api/inventory'
import { fetchBranches } from '@/api/branches'
import { fetchActiveProducts } from '@/api/products'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { formatCurrency } from '@/lib/format'

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
  quantity_ordered: number
  unit_cost: number
}

export function PurchaseOrderFormPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)

  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts() })

  const [supplierId, setSupplierId] = useState<string | null>(null)
  const [branchId, setBranchId] = useState('')
  const [warehouseId, setWarehouseId] = useState('')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [orderDate, setOrderDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [expectedDeliveryDate, setExpectedDeliveryDate] = useState('')
  const [notes, setNotes] = useState('')
  const [rows, setRows] = useState<Row[]>([{ key: crypto.randomUUID(), product_id: null, quantity_ordered: 1, unit_cost: 0 }])

  useEffect(() => {
    if (user?.branch_id) setBranchId(String(user.branch_id))
  }, [user])

  const addRow = () => setRows((prev) => [...prev, { key: crypto.randomUUID(), product_id: null, quantity_ordered: 1, unit_cost: 0 }])
  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))
  const updateRow = (key: string, patch: Partial<Row>) => setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const total = rows.reduce((sum, row) => sum + row.quantity_ordered * row.unit_cost, 0)

  const buildPayload = () => ({
    branch_id: Number(branchId),
    warehouse_id: Number(warehouseId),
    supplier_id: Number(supplierId),
    reference_number: referenceNumber,
    order_date: orderDate,
    expected_delivery_date: expectedDeliveryDate || null,
    notes: notes || null,
    items: rows.filter((row) => row.product_id).map((row) => ({ product_id: row.product_id!, quantity_ordered: row.quantity_ordered, unit_cost: row.unit_cost })),
  })

  const saveDraftMutation = useMutation({
    mutationFn: () => createPurchaseOrder(buildPayload()),
    onSuccess: (po) => {
      toast.success('Purchase order saved as draft')
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      navigate(`/purchase-orders/${po.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const saveAndSubmitMutation = useMutation({
    mutationFn: async () => {
      const po = await createPurchaseOrder(buildPayload())
      return submitPurchaseOrder(po.id)
    },
    onSuccess: (po) => {
      toast.success('Purchase order submitted')
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      navigate(`/purchase-orders/${po.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = supplierId && branchId && warehouseId && referenceNumber && orderDate && rows.some((row) => row.product_id)

  return (
    <div>
      <PageHeader parent="Purchase Orders" title="New Purchase Order" />

      <Card className="mb-4">
        <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5 lg:col-span-2">
            <Label>Supplier</Label>
            <SearchableSelect
              options={(suppliers?.data ?? []).map((supplier) => ({ value: String(supplier.id), label: supplier.name, sublabel: supplier.contact_person ?? undefined }))}
              value={supplierId}
              onChange={setSupplierId}
              placeholder="Select supplier"
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
            <Label>Receiving Warehouse</Label>
            <Select value={warehouseId} onValueChange={setWarehouseId}>
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
            <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="PO-001" />
          </div>
          <div className="space-y-1.5">
            <Label>Order Date</Label>
            <Input type="date" value={orderDate} onChange={(event) => setOrderDate(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Expected Delivery Date</Label>
            <Input type="date" value={expectedDeliveryDate} onChange={(event) => setExpectedDeliveryDate(event.target.value)} />
          </div>
          <div className="space-y-1.5 lg:col-span-2">
            <Label>Notes</Label>
            <Input value={notes} onChange={(event) => setNotes(event.target.value)} />
          </div>
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="space-y-3 p-6">
          <p className="text-sm font-semibold text-foreground">Line Items</p>
          {rows.map((row) => (
            <div key={row.key} className="flex items-center gap-2">
              <div className="flex-1">
                <SearchableSelect
                  options={(products ?? []).map((product) => ({ value: String(product.id), label: product.name, sublabel: product.sku }))}
                  value={row.product_id ? String(row.product_id) : null}
                  onChange={(value) => updateRow(row.key, { product_id: value ? Number(value) : null })}
                  placeholder="Select product"
                />
              </div>
              <Input
                type="number"
                min="1"
                step="1"
                value={row.quantity_ordered}
                onChange={(event) => updateRow(row.key, { quantity_ordered: Number(event.target.value) })}
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
              <span className="w-28 text-right text-sm text-muted-foreground">{formatCurrency(row.quantity_ordered * row.unit_cost)}</span>
              <button type="button" onClick={() => removeRow(row.key)} className="text-muted-foreground hover:text-destructive">
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" onClick={addRow}>
            <Plus className="h-4 w-4" />
            Add Item
          </Button>

          <div className="flex justify-end border-t border-border pt-3 text-base font-bold text-foreground">
            Total: {formatCurrency(total)}
          </div>
        </CardContent>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => navigate(-1)}>
          Cancel
        </Button>
        <Button variant="outline" disabled={!canSubmit || saveDraftMutation.isPending} onClick={() => saveDraftMutation.mutate()}>
          Save Draft
        </Button>
        <Button disabled={!canSubmit || saveAndSubmitMutation.isPending} onClick={() => saveAndSubmitMutation.mutate()}>
          Save & Submit
        </Button>
      </div>
    </div>
  )
}
