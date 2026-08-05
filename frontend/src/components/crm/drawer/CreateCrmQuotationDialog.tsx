import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createCrmDealQuotation, createCrmLeadQuotation, type CrmQuotationLineInput } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { toISODate } from '@/lib/format'
import type { CrmService } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface LineRow {
  crm_service_id: number
  quantity: number
  unit_price: number
}

interface CreateCrmQuotationDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  type: 'lead' | 'deal'
  id: number
  attachedServices: CrmService[]
  queryKeyToInvalidate: unknown[]
}

export function CreateCrmQuotationDialog({ open, onOpenChange, type, id, attachedServices, queryKeyToInvalidate }: CreateCrmQuotationDialogProps) {
  const queryClient = useQueryClient()
  const [rows, setRows] = useState<LineRow[]>([])
  const [validUntil, setValidUntil] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setRows(attachedServices.map((service) => ({ crm_service_id: service.id, quantity: 1, unit_price: service.default_price })))
    setValidUntil(toISODate(new Date(Date.now() + 14 * 24 * 60 * 60 * 1000)))
    setError(null)
  }, [open, attachedServices])

  const mutation = useMutation({
    mutationFn: () => {
      const items: CrmQuotationLineInput[] = rows.map((row) => ({
        crm_service_id: row.crm_service_id,
        quantity: row.quantity,
        unit_price: row.unit_price,
      }))
      const payload = { valid_until: validUntil, items }
      return type === 'lead' ? createCrmLeadQuotation(id, payload) : createCrmDealQuotation(id, payload)
    },
    onSuccess: () => {
      toast.success('Quotation created')
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
      onOpenChange(false)
    },
    onError: (err) => setError(getApiErrorInfo(err).message),
  })

  const availableToAdd = attachedServices.filter((service) => !rows.some((row) => row.crm_service_id === service.id))
  const total = rows.reduce((sum, row) => sum + row.quantity * row.unit_price, 0)

  const updateRow = (index: number, patch: Partial<LineRow>) => {
    setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)))
  }

  const removeRow = (index: number) => {
    setRows((prev) => prev.filter((_, i) => i !== index))
  }

  const addRow = (serviceId: string) => {
    const service = attachedServices.find((s) => s.id === Number(serviceId))
    if (!service) return
    setRows((prev) => [...prev, { crm_service_id: service.id, quantity: 1, unit_price: service.default_price }])
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Create Quotation</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          {rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No interested services attached yet — attach at least one on the Overview tab first.
            </p>
          ) : (
            <div className="overflow-hidden rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">Service</th>
                    <th className="px-3 py-2 text-right">Qty</th>
                    <th className="px-3 py-2 text-right">Unit Price</th>
                    <th className="px-3 py-2 text-right">Line Total</th>
                    <th className="w-8" />
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, index) => {
                    const service = attachedServices.find((s) => s.id === row.crm_service_id)
                    return (
                      <tr key={row.crm_service_id} className="border-t border-border">
                        <td className="px-3 py-2 text-foreground">{service?.name ?? `Service #${row.crm_service_id}`}</td>
                        <td className="px-3 py-2">
                          <Input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={row.quantity}
                            onChange={(e) => updateRow(index, { quantity: Number(e.target.value) })}
                            className="w-20 text-right"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={row.unit_price}
                            onChange={(e) => updateRow(index, { unit_price: Number(e.target.value) })}
                            className="w-24 text-right"
                          />
                        </td>
                        <td className="px-3 py-2 text-right text-foreground">{formatCurrency(row.quantity * row.unit_price)}</td>
                        <td className="px-3 py-2">
                          <button type="button" onClick={() => removeRow(index)} className="text-muted-foreground hover:text-destructive">
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
                <tfoot>
                  <tr className="border-t border-border font-semibold">
                    <td colSpan={3} className="px-3 py-2 text-right text-foreground">
                      Total
                    </td>
                    <td className="px-3 py-2 text-right text-foreground">{formatCurrency(total)}</td>
                    <td />
                  </tr>
                </tfoot>
              </table>
            </div>
          )}

          {availableToAdd.length > 0 && (
            <div className="w-56 space-y-1.5">
              <Label>Add another attached service</Label>
              <Select value="" onValueChange={addRow}>
                <SelectTrigger>
                  <SelectValue placeholder="Select a service" />
                </SelectTrigger>
                <SelectContent>
                  {availableToAdd.map((service) => (
                    <SelectItem key={service.id} value={String(service.id)}>
                      {service.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="w-48 space-y-1.5">
            <Label htmlFor="valid-until">Valid Until</Label>
            <Input id="valid-until" type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} />
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={rows.length === 0 || !validUntil || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Quotation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
