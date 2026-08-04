import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createStockTransfer, fetchWarehouses } from '@/api/inventory'
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
  quantity: number
}

export function StockTransferFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses, enabled: open })
  const { data: products } = useQuery({ queryKey: ['products-all'], queryFn: () => fetchActiveProducts(), enabled: open })

  const [fromWarehouseId, setFromWarehouseId] = useState('')
  const [toWarehouseId, setToWarehouseId] = useState('')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [rows, setRows] = useState<Row[]>([])

  useEffect(() => {
    if (open) {
      setFromWarehouseId('')
      setToWarehouseId('')
      setReferenceNumber('')
      setRows([{ key: crypto.randomUUID(), product_id: null, quantity: 1 }])
    }
  }, [open])

  const addRow = () => setRows((prev) => [...prev, { key: crypto.randomUUID(), product_id: null, quantity: 1 }])
  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))
  const updateRow = (key: string, patch: Partial<Row>) => setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const mutation = useMutation({
    mutationFn: () =>
      createStockTransfer({
        from_warehouse_id: Number(fromWarehouseId),
        to_warehouse_id: Number(toWarehouseId),
        reference_number: referenceNumber,
        items: rows.filter((row) => row.product_id).map((row) => ({ product_id: row.product_id!, quantity: row.quantity })),
      }),
    onSuccess: () => {
      toast.success('Stock transfer created')
      queryClient.invalidateQueries({ queryKey: ['stock-transfers'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = fromWarehouseId && toWarehouseId && fromWarehouseId !== toWarehouseId && referenceNumber && rows.some((row) => row.product_id)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>New Stock Transfer</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Reference Number</Label>
            <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="TRF-001" />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>From Warehouse</Label>
              <Select value={fromWarehouseId} onValueChange={setFromWarehouseId}>
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
              <Label>To Warehouse</Label>
              <Select value={toWarehouseId} onValueChange={setToWarehouseId}>
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
                  />
                </div>
                <Input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={row.quantity}
                  onChange={(event) => updateRow(row.key, { quantity: Number(event.target.value) })}
                  className="w-28"
                />
                <button type="button" onClick={() => removeRow(row.key)} className="text-muted-foreground hover:text-destructive">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={addRow}>
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
            Create Transfer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
