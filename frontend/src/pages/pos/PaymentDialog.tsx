import { useEffect, useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'

import { formatCurrency } from '@/lib/format'
import { round2 } from '@/pages/pos/types'
import type { PaymentType } from '@/types/pos'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export interface PaymentRow {
  key: string
  paymentTypeId: number | null
  amount: string
}

interface PaymentDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  totalDue: number
  paymentTypes: PaymentType[]
  allowPartial: boolean
  isSubmitting: boolean
  onComplete: (rows: { paymentTypeId: number; amount: number }[]) => void
}

export function PaymentDialog({ open, onOpenChange, totalDue, paymentTypes, allowPartial, isSubmitting, onComplete }: PaymentDialogProps) {
  const [rows, setRows] = useState<PaymentRow[]>([])

  useEffect(() => {
    if (open) {
      setRows([{ key: crypto.randomUUID(), paymentTypeId: paymentTypes[0]?.id ?? null, amount: totalDue.toFixed(2) }])
    }
  }, [open, totalDue, paymentTypes])

  const paidSoFar = round2(rows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0))
  const remaining = round2(totalDue - paidSoFar)
  const canComplete = rows.every((row) => row.paymentTypeId && Number(row.amount) > 0) && (allowPartial ? paidSoFar > 0 : remaining === 0)

  const addRow = () => {
    setRows((prev) => [
      ...prev,
      { key: crypto.randomUUID(), paymentTypeId: paymentTypes[0]?.id ?? null, amount: Math.max(remaining, 0).toFixed(2) },
    ])
  }

  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))

  const updateRow = (key: string, patch: Partial<PaymentRow>) =>
    setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Take Payment</DialogTitle>
        </DialogHeader>

        <div className="rounded-lg bg-hero-gradient p-4 text-white">
          <p className="text-xs text-white/80">Total Due</p>
          <p className="text-2xl font-bold">{formatCurrency(totalDue)}</p>
        </div>

        <div className="space-y-3">
          {rows.map((row) => (
            <div key={row.key} className="flex items-end gap-2">
              <div className="flex-1 space-y-1.5">
                <Select
                  value={row.paymentTypeId ? String(row.paymentTypeId) : undefined}
                  onValueChange={(value) => updateRow(row.key, { paymentTypeId: Number(value) })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Payment type" />
                  </SelectTrigger>
                  <SelectContent>
                    {paymentTypes.map((paymentType) => (
                      <SelectItem key={paymentType.id} value={String(paymentType.id)}>
                        {paymentType.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="w-32 space-y-1.5">
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={row.amount}
                  onChange={(event) => updateRow(row.key, { amount: event.target.value })}
                />
              </div>
              {rows.length > 1 && (
                <button
                  type="button"
                  onClick={() => removeRow(row.key)}
                  className="mb-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-danger-bg hover:text-danger"
                  aria-label="Remove payment row"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              )}
            </div>
          ))}

          <button type="button" onClick={addRow} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
            <Plus className="h-3.5 w-3.5" />
            Add split payment
          </button>
        </div>

        <div className="flex items-center justify-between rounded-lg bg-muted px-3 py-2 text-sm">
          <span className="text-muted-foreground">Remaining</span>
          <span className={remaining > 0 ? 'font-semibold text-danger' : 'font-semibold text-success'}>{formatCurrency(remaining)}</span>
        </div>
        {allowPartial && remaining > 0 && (
          <p className="text-xs text-muted-foreground">
            This customer has credit terms — the remaining balance will be tracked as receivable.
          </p>
        )}

        <Button
          className="w-full"
          disabled={!canComplete || isSubmitting}
          onClick={() =>
            onComplete(
              rows
                .filter((row) => row.paymentTypeId && Number(row.amount) > 0)
                .map((row) => ({ paymentTypeId: row.paymentTypeId!, amount: Number(row.amount) })),
            )
          }
        >
          {isSubmitting ? 'Completing…' : 'Complete Sale'}
        </Button>
      </DialogContent>
    </Dialog>
  )
}
