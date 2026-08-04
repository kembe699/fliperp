import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createStockAdjustment, fetchStockLevels, fetchWarehouses } from '@/api/inventory'
import { fetchActiveProducts } from '@/api/products'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface Row {
  key: string
  product_id: number | null
  counted_quantity: number
}

export function StockAdjustmentFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses, enabled: open })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts(), enabled: open })

  const [warehouseId, setWarehouseId] = useState('')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [reason, setReason] = useState('')
  const [rows, setRows] = useState<Row[]>([])

  const { data: stockLevels } = useQuery({
    queryKey: ['stock-levels', warehouseId],
    queryFn: () => fetchStockLevels({ warehouse_id: Number(warehouseId) }),
    enabled: open && !!warehouseId,
  })

  useEffect(() => {
    if (open) {
      setWarehouseId('')
      setReferenceNumber('')
      setReason('')
      setRows([{ key: crypto.randomUUID(), product_id: null, counted_quantity: 0 }])
    }
  }, [open])

  const systemQuantity = (productId: number | null) =>
    productId ? (stockLevels?.find((level) => level.product_id === productId)?.quantity_on_hand ?? 0) : 0

  const addRow = () => setRows((prev) => [...prev, { key: crypto.randomUUID(), product_id: null, counted_quantity: 0 }])
  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))
  const updateRow = (key: string, patch: Partial<Row>) => setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const mutation = useMutation({
    mutationFn: () =>
      createStockAdjustment({
        warehouse_id: Number(warehouseId),
        reference_number: referenceNumber,
        reason: reason || undefined,
        items: rows.filter((row) => row.product_id).map((row) => ({ product_id: row.product_id!, counted_quantity: row.counted_quantity })),
      }),
    onSuccess: () => {
      toast.success('Stock adjustment created')
      queryClient.invalidateQueries({ queryKey: ['stock-adjustments'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = warehouseId && referenceNumber && rows.some((row) => row.product_id)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>New Stock Adjustment</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Reference Number</Label>
              <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="ADJ-001" />
            </div>
            <div className="space-y-1.5">
              <Label>Warehouse</Label>
              <Select value={warehouseId} onValueChange={(value) => { setWarehouseId(value); setRows([{ key: crypto.randomUUID(), product_id: null, counted_quantity: 0 }]) }}>
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
          </div>

          <div className="space-y-1.5">
            <Label>Reason</Label>
            <Input value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Cycle count, damage, etc." />
          </div>

          <div className="space-y-2">
            <Label>Items</Label>
            {rows.map((row) => (
              <div key={row.key} className="flex items-center gap-2">
                <div className="flex-1">
                  <SearchableSelect
                    options={(products ?? []).map((product) => ({ value: String(product.id), label: product.name, sublabel: product.sku }))}
                    value={row.product_id ? String(row.product_id) : null}
                    onChange={(value) => updateRow(row.key, { product_id: value ? Number(value) : null })}
                    placeholder="Select product"
                    disabled={!warehouseId}
                  />
                </div>
                <div className="w-24 text-center text-sm text-muted-foreground">System: {systemQuantity(row.product_id)}</div>
                <Input
                  type="number"
                  min="0"
                  step="0.01"
                  value={row.counted_quantity}
                  onChange={(event) => updateRow(row.key, { counted_quantity: Number(event.target.value) })}
                  className="w-28"
                  placeholder="Counted"
                />
                <button type="button" onClick={() => removeRow(row.key)} className="text-muted-foreground hover:text-destructive">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={addRow} disabled={!warehouseId}>
              <Plus className="h-4 w-4" />
              Add Item
            </Button>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Adjustment
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
