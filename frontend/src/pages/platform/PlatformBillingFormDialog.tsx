import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createPlatformInvoice, createPlatformQuotation } from '@/api/platform'
import { fetchActiveProducts } from '@/api/products'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import type { PlatformLineItemInput } from '@/types/platform'

import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface DraftLine {
  product_id: number | null
  quantity: number
  unit_price: number | null
}

const emptyLine: DraftLine = { product_id: null, quantity: 1, unit_price: null }

interface PlatformBillingFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  clientId: number
  kind: 'invoice' | 'quotation'
}

export function PlatformBillingFormDialog({ open, onOpenChange, clientId, kind }: PlatformBillingFormDialogProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [dateValue, setDateValue] = useState('')
  const [notes, setNotes] = useState('')
  const [lines, setLines] = useState<DraftLine[]>([{ ...emptyLine }])

  const { data: products } = useQuery({ queryKey: ['products', 'active'], queryFn: () => fetchActiveProducts() })

  const reset = () => {
    setDateValue('')
    setNotes('')
    setLines([{ ...emptyLine }])
  }

  const mutation = useMutation({
    mutationFn: async (): Promise<{ id: number }> => {
      const items: PlatformLineItemInput[] = lines
        .filter((line) => line.product_id)
        .map((line) => ({
          product_id: line.product_id!,
          quantity: line.quantity,
          unit_price: line.unit_price,
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

  const updateLine = (index: number, patch: Partial<DraftLine>) => {
    setLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)))
  }

  const selectProduct = (index: number, productId: number) => {
    const product = products?.find((p) => p.id === productId)
    updateLine(index, { product_id: productId, unit_price: product?.selling_price ?? null })
  }

  const total = lines.reduce((sum, line) => {
    if (!line.product_id) return sum
    const product = products?.find((p) => p.id === line.product_id)
    const price = line.unit_price ?? product?.selling_price ?? 0
    return sum + price * line.quantity
  }, 0)

  const canSubmit = dateValue && lines.some((line) => line.product_id)

  return (
    <Dialog open={open} onOpenChange={(next) => { if (!next) reset(); onOpenChange(next) }}>
      <DialogContent className="max-w-lg">
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

          <div className="space-y-2">
            <Label>Line Items</Label>
            {lines.map((line, index) => (
              <div key={index} className="flex items-center gap-2">
                <Select value={line.product_id ? String(line.product_id) : undefined} onValueChange={(value) => selectProduct(index, Number(value))}>
                  <SelectTrigger className="h-9 flex-1 text-sm">
                    <SelectValue placeholder="Select product…" />
                  </SelectTrigger>
                  <SelectContent>
                    {products?.map((product) => (
                      <SelectItem key={product.id} value={String(product.id)}>
                        {product.name} — {formatCurrency(product.selling_price)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Input
                  type="number"
                  min={1}
                  className="h-9 w-16"
                  value={line.quantity}
                  onChange={(event) => updateLine(index, { quantity: Number(event.target.value) || 1 })}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-9 w-9 shrink-0 text-muted-foreground"
                  disabled={lines.length === 1}
                  onClick={() => setLines((prev) => prev.filter((_, i) => i !== index))}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={() => setLines((prev) => [...prev, { ...emptyLine }])}>
              <Plus className="h-3.5 w-3.5" />
              Add Line
            </Button>
          </div>

          <div className="flex items-center justify-between border-t border-border pt-3 text-sm font-semibold text-foreground">
            <span>Estimated Total</span>
            <span>{formatCurrency(total)}</span>
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
